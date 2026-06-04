<?php

namespace App\Http\Controllers;

use App\Services\InventoryPriceChecker;
use App\Services\SteamInventoryService;
use App\Services\TrackedInventoryStore;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\ExchangeRateStore;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use App\Support\SiteMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PublicInventoryController extends Controller
{
    public function __construct(
        private TrackedInventoryStore $store,
    ) {}

    public function landing(Request $request): View
    {
        $cs2cap = app(\App\Services\Cs2CapService::class);

        return view('public.landing', [
            'meta' => SiteMeta::make([
                'canonical' => route('public.landing'),
                'url' => route('public.landing'),
            ]),
            'checkResult' => null,
            'checkError' => null,
            'submittedUrl' => '',
            'empireEnabled' => Cs2PriceFeatures::empireEnabled() || $cs2cap->isConfigured(),
        ]);
    }

    public function guestCheckStart(Request $request, InventoryPriceChecker $checker, SteamInventoryService $steam): JsonResponse
    {
        $request->validate([
            'steam_url' => ['required', 'string', 'max:2000'],
        ]);

        $url = trim((string) $request->input('steam_url'));

        try {
            $steam->parseInventoryUrl($url);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $cooldownKey = 'guest-price-check:'.$request->ip();
        $cooldownSeconds = max(60, (int) config('cs2price.guest_check_cooldown_seconds', 300));

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return response()->json([
                'ok' => false,
                'error' => sprintf(
                    'Mỗi IP chỉ tra được 1 kho / %d phút. Thử lại sau %s.',
                    (int) ceil($cooldownSeconds / 60),
                    $this->formatCooldownWait(RateLimiter::availableIn($cooldownKey))
                ),
            ], 429);
        }

        $this->extendExecutionTime();

        try {
            ['parsed' => $parsed, 'bundle' => $bundle] = $checker->fetchSteamBundleForUrl($url);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        RateLimiter::hit($cooldownKey, $cooldownSeconds);

        $steamItems = $checker->steamItemsWorthPricing($bundle['items']);
        $token = (string) Str::uuid();
        $cacheMinutes = max(5, (int) config('cs2price.guest_check_cache_minutes', 15));

        Cache::put($this->guestCheckCacheKey($token), [
            'ip' => $request->ip(),
            'parsed' => $parsed,
            'bundle' => [
                'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
                'steam_avatar_url' => $avatarDisplay ?? $bundle['steam_avatar_url'] ?? null,
                'inventory_source' => $bundle['inventory_source'] ?? null,
                'inventory_fallback_message' => $bundle['inventory_fallback_message'] ?? null,
            ],
            'steam_items' => $steamItems,
        ], now()->addMinutes($cacheMinutes));

        $images = app(\App\Services\ItemImageService::class);

        $displayItems = array_map(static function (array $item) use ($images) {
            $item = $images->enrichItemRowForDisplay($item);

            return [
                'assetid' => $item['assetid'] ?? null,
                'name' => $item['name'] ?? '',
                'market_hash_name' => $item['market_hash_name'] ?? '',
                'icon_url' => $item['icon_url'] ?? null,
                'steam_icon_hint' => $item['steam_icon_hint'] ?? '',
                'amount' => $item['amount'] ?? 1,
                'tradable' => $item['tradable'] ?? true,
            ];
        }, $steamItems);

        $avatarDisplay = $images->avatarUrlForDisplay(
            $parsed['steam_id'],
            $bundle['steam_avatar_url'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'token' => $token,
            'empire_enabled' => Cs2PriceFeatures::empireEnabled() || app(\App\Services\Cs2CapService::class)->isConfigured(),
            'empire_usd_reference' => \App\Support\PricingTier::current()->usesCs2CapEmpireOnly(),
            'pricing_tier' => \App\Support\PricingTier::current()->value,
            'batch_size' => max(4, (int) config('cs2price.guest_check_batch_size', 12)),
            'min_item_cny' => \App\Support\InventoryItemFilter::minCnyUnitValue(),
            'rates' => [
                'cny_to_vnd' => ExchangeRateStore::cnyToVnd(),
                'vnd_to_usd' => ExchangeRateStore::vndToUsd(),
            ],
            'inventory' => [
                'label' => $bundle['steam_persona_name'] ?? $parsed['label'] ?? 'Steam',
                'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
                'steam_avatar_url' => $avatarDisplay ?? $bundle['steam_avatar_url'] ?? null,
                'steam_id' => $parsed['steam_id'],
                'url' => $parsed['url'],
            ],
            'item_count' => count($displayItems),
            'items' => $displayItems,
            'inventory_source' => $bundle['inventory_source'] ?? null,
            'inventory_fallback_message' => $bundle['inventory_fallback_message'] ?? null,
        ]);
    }

    public function guestCheckPrices(Request $request, InventoryPriceChecker $checker): JsonResponse
    {
        $batchSize = max(4, (int) config('cs2price.guest_check_batch_size', 12));

        $request->validate([
            'token' => ['required', 'string', 'uuid'],
            'hashes' => ['required', 'array', 'min:1', 'max:'.$batchSize],
            'hashes.*' => ['required', 'string', 'max:500'],
        ]);

        $token = (string) $request->input('token');
        $cached = Cache::get($this->guestCheckCacheKey($token));

        if (! is_array($cached) || ($cached['ip'] ?? '') !== $request->ip()) {
            return response()->json(['ok' => false, 'error' => 'Phiên tra giá hết hạn. Vui lòng tra lại từ đầu.'], 410);
        }

        $this->extendExecutionTime();

        $hashes = array_values(array_unique($request->input('hashes', [])));
        $steamItems = $cached['steam_items'] ?? [];

        try {
            $rows = $checker->priceSteamItems($steamItems, null, $hashes);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $images = app(\App\Services\ItemImageService::class);
        $rows = array_map(fn (array $row) => $images->enrichItemRowForDisplay($row), $rows);

        $totalItems = count($steamItems);

        return response()->json([
            'ok' => true,
            'items' => $rows,
            'progress' => [
                'batch' => count($hashes),
                'total' => $totalItems,
            ],
        ]);
    }

    public function guestItemImage(Request $request, \App\Services\ItemImageService $images): JsonResponse
    {
        $request->validate([
            'market_hash_name' => ['required', 'string', 'max:500'],
        ]);

        $cooldownKey = 'guest-item-image:'.$request->ip();
        // tránh abuse: max 60 req / phút / IP
        if (RateLimiter::tooManyAttempts($cooldownKey, 60)) {
            return response()->json(['ok' => false, 'error' => 'Rate limited'], 429);
        }
        RateLimiter::hit($cooldownKey, 60);

        $name = trim((string) $request->input('market_hash_name'));
        $iconHint = $request->query('icon');
        $preferCatalog = $request->query('prefer') === 'catalog';
        $resolved = $images->resolveForBrowser(
            $name,
            is_string($iconHint) ? $iconHint : null,
            $preferCatalog,
        );

        return response()->json([
            'ok' => $resolved['ok'],
            'image_url' => $resolved['image_url'],
            'source' => $resolved['source'],
        ]);
    }

    public function guestItemImageStream(Request $request, \App\Services\ItemImageService $images): Response
    {
        $request->validate([
            'market_hash_name' => ['required', 'string', 'max:500'],
        ]);

        $cooldownKey = 'guest-item-image-stream:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cooldownKey, 120)) {
            return response('Rate limited', 429);
        }
        RateLimiter::hit($cooldownKey, 60);

        $iconHint = $request->query('icon');

        return $images->streamSteamImage(
            (string) $request->query('market_hash_name'),
            is_string($iconHint) ? $iconHint : null,
        );
    }

    public function guestSteamAvatarStream(Request $request, \App\Services\ItemImageService $images): Response
    {
        $request->validate([
            'steam_id' => ['required', 'string', 'regex:/^\d{17}$/'],
        ]);

        $cooldownKey = 'guest-steam-avatar-stream:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cooldownKey, 120)) {
            return response('Rate limited', 429);
        }
        RateLimiter::hit($cooldownKey, 60);

        return $images->streamSteamAvatar((string) $request->query('steam_id'));
    }

    private function guestCheckCacheKey(string $token): string
    {
        return 'guest_check:'.$token;
    }

    private function extendExecutionTime(): void
    {
        $seconds = max(120, (int) config('cs2price.check_max_execution_seconds', 600));
        @set_time_limit($seconds);
        @ini_set('max_execution_time', (string) $seconds);
    }

    private function formatCooldownWait(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' giây';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($remainingSeconds === 0) {
            return $minutes.' phút';
        }

        return $minutes.' phút '.$remainingSeconds.' giây';
    }

    public function pricing(): View
    {
        return view('public.pricing', [
            'meta' => SiteMeta::forRequest('Bảng giá — CheckPrice CS2'),
        ]);
    }

    public function index(): View
    {
        $query = trim((string) request('q', ''));

        $inventories = $this->store->publicInventories();

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $inventories = $inventories->filter(function (object $inv) use ($needle, $query) {
                return str_contains(mb_strtolower((string) ($inv->label ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($inv->steam_persona_name ?? '')), $needle)
                    || str_contains((string) ($inv->steam_id ?? ''), $query);
            })->values();
        }

        $inventories = $inventories->map(function (object $inv) {
            $inv->display_items = EmpireItemEnricher::enrich(
                InventorySnapshotReader::itemsFromInventory($inv),
                fetchMissing: false,
            );
            $images = app(\App\Services\ItemImageService::class);
            $inv->display_items = array_map(
                fn (array $item) => $images->enrichItemRowForDisplay($item),
                $inv->display_items,
            );
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        return view('public.index', [
            'inventories' => $inventories,
            'searchQuery' => $query,
            'meta' => SiteMeta::forRequest('Bảng giá kho CS2'),
        ]);
    }

    /** URL cũ /kho/{id} → danh sách kho công khai (không còn trang chi tiết riêng). */
    public function redirectLegacyInventory(int $inventory): \Illuminate\Http\RedirectResponse
    {
        abort_unless($this->store->findPublic($inventory), 404);

        return redirect()->to(route('public.inventories').'#kho-'.$inventory, 301);
    }
}
