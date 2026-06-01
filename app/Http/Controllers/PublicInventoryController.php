<?php

namespace App\Http\Controllers;

use App\Services\InventoryPriceChecker;
use App\Services\SteamInventoryService;
use App\Services\TrackedInventoryStore;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use App\Support\SiteMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
            $inv->display_items = InventorySnapshotReader::itemsFromInventory($inv);
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

        $items = InventorySnapshotReader::itemsFromInventory($row);

        return view('public.show', [
            'inventory' => $row,
            'items' => $items,
            'weaponStats' => InventoryWeaponStats::summarize($items),
            'meta' => SiteMeta::forInventory($row),
        ]);
    }
}
