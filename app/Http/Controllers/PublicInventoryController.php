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

    public function landing(Request $request, InventoryPriceChecker $checker, SteamInventoryService $steam): View
    {
        $checkResult = null;
        $checkError = null;
        $submittedUrl = old('steam_url', '');

        if ($request->isMethod('post')) {
            $submittedUrl = trim((string) $request->input('steam_url', ''));
            $request->validate([
                'steam_url' => ['required', 'string', 'max:2000'],
            ]);

            try {
                $steam->parseInventoryUrl($submittedUrl);
            } catch (RuntimeException $e) {
                $checkError = $e->getMessage();
            }

            if ($checkError === null) {
                $cooldownKey = 'guest-price-check:'.$request->ip();
                $cooldownSeconds = max(60, (int) config('cs2price.guest_check_cooldown_seconds', 300));

                if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
                    $checkError = sprintf(
                        'Mỗi IP chỉ tra được 1 kho / %d phút. Thử lại sau %s.',
                        (int) ceil($cooldownSeconds / 60),
                        $this->formatCooldownWait(RateLimiter::availableIn($cooldownKey))
                    );
                } else {
                    RateLimiter::hit($cooldownKey, $cooldownSeconds);

                    $this->extendExecutionTime();

                    try {
                        $result = $checker->checkUrl($submittedUrl);
                        $result['items'] = EmpireItemEnricher::enrich($result['items'], fetchMissing: true);
                        $result['empire_priced_count'] = collect($result['items'])
                            ->whereNotNull('empire_price_coins')->count();
                        $result['total_empire_cny'] = round((float) collect($result['items'])
                            ->sum(fn ($row) => (float) ($row['line_total_empire_cny'] ?? 0)), 2);
                        $checkResult = [
                            'inventory' => (object) [
                                'label' => $result['steam_persona_name'] ?? $result['label'],
                                'steam_persona_name' => $result['steam_persona_name'] ?? null,
                                'steam_avatar_url' => $result['steam_avatar_url'] ?? null,
                                'steam_id' => $result['steam_id'],
                                'url' => $result['url'],
                                'last_total_cny' => $result['total_cny'],
                                'last_total_vnd' => $result['total_vnd'],
                            ],
                            'items' => $result['items'],
                            'item_count' => $result['item_count'],
                            'priced_count' => $result['priced_count'],
                            'failed_count' => $result['failed_count'],
                            'empire_priced_count' => $result['empire_priced_count'] ?? 0,
                            'total_empire_cny' => $result['total_empire_cny'] ?? 0,
                            'sell_compare_buff_wins' => $result['sell_compare_buff_wins'] ?? 0,
                            'sell_compare_empire_wins' => $result['sell_compare_empire_wins'] ?? 0,
                        ];
                    } catch (RuntimeException $e) {
                        $checkError = $e->getMessage();
                    }
                }
            }
        }

        return view('public.landing', [
            'meta' => SiteMeta::make([
                'canonical' => route('public.landing'),
                'url' => route('public.landing'),
            ]),
            'checkResult' => $checkResult,
            'checkError' => $checkError,
            'submittedUrl' => $submittedUrl,
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

        RateLimiter::hit($cooldownKey, $cooldownSeconds);
        $this->extendExecutionTime();

        try {
            ['parsed' => $parsed, 'bundle' => $bundle] = $checker->fetchSteamBundleForUrl($url);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $steamItems = $bundle['items'];
        $token = (string) Str::uuid();
        $cacheMinutes = max(5, (int) config('cs2price.guest_check_cache_minutes', 15));

        Cache::put($this->guestCheckCacheKey($token), [
            'ip' => $request->ip(),
            'parsed' => $parsed,
            'bundle' => [
                'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
                'steam_avatar_url' => $bundle['steam_avatar_url'] ?? null,
                'inventory_source' => $bundle['inventory_source'] ?? null,
                'inventory_fallback_message' => $bundle['inventory_fallback_message'] ?? null,
            ],
            'steam_items' => $steamItems,
        ], now()->addMinutes($cacheMinutes));

        $displayItems = array_map(static fn (array $item) => [
            'assetid' => $item['assetid'] ?? null,
            'name' => $item['name'] ?? '',
            'market_hash_name' => $item['market_hash_name'] ?? '',
            'icon_url' => $item['icon_url'] ?? null,
            'amount' => $item['amount'] ?? 1,
            'tradable' => $item['tradable'] ?? true,
        ], $steamItems);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'empire_enabled' => Cs2PriceFeatures::empireEnabled(),
            'batch_size' => max(4, (int) config('cs2price.guest_check_batch_size', 12)),
            'rates' => [
                'cny_to_vnd' => ExchangeRateStore::cnyToVnd(),
                'vnd_to_usd' => ExchangeRateStore::vndToUsd(),
            ],
            'inventory' => [
                'label' => $bundle['steam_persona_name'] ?? $parsed['label'] ?? 'Steam',
                'steam_persona_name' => $bundle['steam_persona_name'] ?? null,
                'steam_avatar_url' => $bundle['steam_avatar_url'] ?? null,
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
            $rows = $checker->priceSteamItems($steamItems, 'guest', $hashes);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

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
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        return view('public.index', [
            'inventories' => $inventories,
            'searchQuery' => $query,
            'meta' => SiteMeta::forRequest('Bảng giá kho CS2'),
        ]);
    }

    public function show(int $inventory): View
    {
        $row = $this->store->findPublic($inventory);
        abort_unless($row, 404);

        $items = EmpireItemEnricher::enrich(
            InventorySnapshotReader::itemsFromInventory($row),
            fetchMissing: true,
        );

        return view('public.show', [
            'inventory' => $row,
            'items' => $items,
            'weaponStats' => InventoryWeaponStats::summarize($items),
            'meta' => SiteMeta::forInventory($row),
        ]);
    }
}
