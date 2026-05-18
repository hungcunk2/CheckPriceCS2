<?php

namespace App\Http\Controllers;

use App\Services\TrackedInventoryStore;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use App\Support\SiteMeta;
use Illuminate\View\View;

class PublicInventoryController extends Controller
{
    public function __construct(
        private TrackedInventoryStore $store,
    ) {}

    public function index(): View
    {
        $inventories = $this->store->publicInventories()->map(function (object $inv) {
            $inv->display_items = InventorySnapshotReader::itemsFromInventory($inv);
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        return view('public.index', [
            'inventories' => $inventories,
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
