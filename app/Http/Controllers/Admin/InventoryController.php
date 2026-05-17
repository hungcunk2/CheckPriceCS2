<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryPriceChecker;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Support\InventoryResultPersister;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InventoryController extends Controller
{
    public function __construct(
        private TrackedInventoryStore $store,
        private InventoryResultPersister $persister,
        private PriceHistoryService $priceHistory,
    ) {}

    public function index(): View
    {
        $inventories = $this->store->all()->map(function (object $inv) {
            $items = InventorySnapshotReader::itemsFromInventory($inv);
            $inv->display_items = $this->priceHistory->enrichItems($items);
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        return view('admin.inventories.index', [
            'inventories' => $inventories,
            'buffConfigured' => filled(config('cs2price.buff_session')),
            'cnyToVnd' => config('cs2price.cny_to_vnd'),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventories.form', [
            'inventory' => null,
        ]);
    }

    public function store(Request $request, InventoryPriceChecker $checker): RedirectResponse
    {
        $validated = $this->validateForm($request);

        $row = $this->store->upsert([
            'label' => $validated['label'],
            'url' => $validated['url'],
            'is_public' => $request->boolean('is_public', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
        }

        return redirect()
            ->route('admin.inventories.index')
            ->with('success', 'Đã lưu kho đồ.');
    }

    public function edit(int $inventory): View|RedirectResponse
    {
        $row = $this->store->find($inventory);
        if (! $row) {
            return redirect()->route('admin.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        return view('admin.inventories.form', [
            'inventory' => $row,
        ]);
    }

    public function update(Request $request, int $inventory, InventoryPriceChecker $checker): RedirectResponse
    {
        $row = $this->store->find($inventory);
        if (! $row) {
            return redirect()->route('admin.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        $validated = $this->validateForm($request);

        $this->store->upsert([
            'label' => $validated['label'],
            'url' => $validated['url'],
            'is_public' => $request->boolean('is_public'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ], $inventory);

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
        }

        return redirect()
            ->route('admin.inventories.index')
            ->with('success', 'Đã cập nhật kho đồ.');
    }

    public function destroy(int $inventory): RedirectResponse
    {
        $this->store->delete($inventory);

        return redirect()
            ->route('admin.inventories.index')
            ->with('success', 'Đã xóa kho khỏi danh sách.');
    }

    public function refresh(Request $request, int $inventory, InventoryPriceChecker $checker): JsonResponse|RedirectResponse
    {
        $row = $this->store->find($inventory);
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho.'], 404);
        }

        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl($row->url, $row->label ?? null);
            $this->persister->persist($result, $inventory, (bool) ($row->is_public ?? true));

            if ($request->wantsJson()) {
                return response()->json(['ok' => true, 'message' => 'Đã cập nhật giá.']);
            }

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', 'Đã cập nhật giá Buff163.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_public' => ['sometimes', 'boolean'],
            'check_now' => ['sometimes', 'boolean'],
        ]);
    }

    private function runCheckAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl($url, $label);
            $row = $this->store->find($id);
            $this->persister->persist($result, $id, $row ? (bool) ($row->is_public ?? true) : true);

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', 'Đã lưu và cập nhật giá Buff163.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.inventories.edit', $id)
                ->with('error', $e->getMessage());
        }
    }

    private function extendExecutionTime(): void
    {
        $seconds = (int) config('cs2price.check_max_execution_seconds', 600);
        if ($seconds > 0) {
            @set_time_limit($seconds);
            @ini_set('max_execution_time', (string) $seconds);
        }
    }
}
