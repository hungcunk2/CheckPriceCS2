<?php

namespace App\Http\Controllers;

use App\Services\TrackedInventoryStore;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
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
            $inv->display_held_items = InventorySnapshotReader::heldItemsFromInventory($inv);
            $inv->held_total_cny = InventorySnapshotReader::heldTotalCnyFromInventory($inv);
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
        $heldItems = InventorySnapshotReader::heldItemsFromInventory($row);

        return view('public.show', [
            'inventory' => $row,
            'items' => $items,
            'heldItems' => $heldItems,
            'heldTotalCny' => InventorySnapshotReader::heldTotalCnyFromInventory($row),
            'weaponStats' => InventoryWeaponStats::summarize($items),
        ]);
    }

}
