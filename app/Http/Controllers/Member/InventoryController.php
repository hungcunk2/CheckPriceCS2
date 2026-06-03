<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Cs2CapCatalogService;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Support\Buff163AccountPool;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use App\Support\SiteMeta;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private TrackedInventoryStore $store,
        private PriceHistoryService $priceHistory,
    ) {}

    public function index(): View
    {
        $inventories = $this->store->all()->map(function (object $inv) {
            $items = EmpireItemEnricher::enrich(
                InventorySnapshotReader::itemsFromInventory($inv),
                fetchMissing: false,
            );
            $inv->display_items = $this->priceHistory->enrichItems($items);
            $hashes = array_values(array_unique(array_column($inv->display_items, 'market_hash_name')));
            $catalogMap = app(Cs2CapCatalogService::class)->cachedImageUrlsForHashes($hashes);
            $inv->display_items = array_map(static function (array $item) use ($catalogMap) {
                $hash = (string) ($item['market_hash_name'] ?? '');
                if ($hash !== '' && isset($catalogMap[$hash])) {
                    $item['icon_url'] = $catalogMap[$hash];
                }

                return $item;
            }, $inv->display_items);
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        $user = auth()->user();

        return view('member.inventories.index', [
            'inventories' => $inventories,
            'buffConfigured' => Buff163AccountPool::isConfigured(),
            'empireEnabled' => Cs2PriceFeatures::empireEnabled(),
            'user' => $user,
            'hasActiveSubscription' => $user?->hasActiveSubscription() ?? false,
            'meta' => SiteMeta::forRequest('Kho đồ của bạn'),
        ]);
    }
}
