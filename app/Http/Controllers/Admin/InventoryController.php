<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryPriceChecker;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Services\ItemImageService;
use App\Support\Buff163AccountPool;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\InventoryResultPersister;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
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
            $items = EmpireItemEnricher::enrich(
                InventorySnapshotReader::itemsFromInventory($inv),
                fetchMissing: false,
            );
            $inv->display_items = $this->priceHistory->enrichItems($items);
            $images = app(ItemImageService::class);
            $inv->display_items = array_map(static function (array $item) use ($images) {
                $hash = (string) ($item['market_hash_name'] ?? '');
                $url = $images->iconUrlForDisplay($hash, $item['icon_url'] ?? null);
                if ($url !== null) {
                    $item['icon_url'] = $url;
                }

                return $item;
            }, $inv->display_items);
            $inv->weapon_stats = InventoryWeaponStats::summarize($inv->display_items);

            return $inv;
        });

        return view('admin.inventories.index', [
            'inventories' => $inventories,
            'buffConfigured' => Buff163AccountPool::isConfigured(),
            'empireEnabled' => Cs2PriceFeatures::empireEnabled(),
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
            'is_public' => $request->boolean('is_public', false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'trade_at' => $this->parseTradeAtFromRequest($request),
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
            'trade_at' => $this->parseTradeAtFromRequest($request),
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
            $result = $checker->checkUrl(
                $row->url,
                $row->label ?? null,
                // Người dùng bấm đồng bộ: ép refresh kho ngay.
                refreshSteam: true,
                empireMode: 'admin',
            );
            $this->persister->persist($result, $inventory, (bool) ($row->is_public ?? false));

            if ($request->wantsJson()) {
                $empireNote = '';
                if (Cs2PriceFeatures::empireEnabled()) {
                    $empireCount = (int) ($result['empire_priced_count'] ?? 0);
                    $empireNote = ', Empire: '.$empireCount.' skin';
                    if ($empireCount === 0) {
                        $empireNote .= ' (kiểm tra API key Empire / EMPIRE_ENABLED)';
                    }
                }

                return response()->json([
                    'ok' => true,
                    'message' => sprintf(
                        'Đã cập nhật — %d/%d skin có giá Buff%s.',
                        (int) $result['priced_count'],
                        (int) $result['item_count'],
                        $empireNote
                    ),
                    'inventory_id' => $inventory,
                    'item_count' => (int) $result['item_count'],
                    'priced_count' => (int) $result['priced_count'],
                    'empire_priced_count' => (int) ($result['empire_priced_count'] ?? 0),
                    'total_cny' => (float) $result['total_cny'],
                    'total_empire_cny' => (float) ($result['total_empire_cny'] ?? 0),
                    'last_checked_at' => Carbon::now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'),
                    'buff_price_html' => $this->renderInventoryBuffPriceCell($result),
                    'empire_price_html' => $this->renderInventoryEmpirePriceCell($result),
                ]);
            }

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', 'Đã cập nhật giá.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            $message = config('app.debug')
                ? $e->getMessage()
                : 'Lỗi server khi check giá — xem storage/logs/laravel.log';

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 500);
            }

            return back()->with('error', $message);
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
            'trade_at_date' => ['nullable', 'date'],
            'trade_at_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'trade_at_minute' => ['nullable', 'integer', 'min:0', 'max:59'],
            'is_public' => ['sometimes', 'boolean'],
            'check_now' => ['sometimes', 'boolean'],
        ]);
    }

    private function parseTradeAtFromRequest(Request $request): ?Carbon
    {
        $date = $request->input('trade_at_date');
        if ($date === null || trim((string) $date) === '') {
            return null;
        }

        $hour = (int) $request->input('trade_at_hour', 0);
        $minute = (int) $request->input('trade_at_minute', 0);

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %02d:%02d', $date, $hour, $minute),
            'Asia/Ho_Chi_Minh'
        )->utc();
    }

    private function runCheckAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl($url, $label, refreshSteam: true, empireMode: 'admin');
            $row = $this->store->find($id);
            $this->persister->persist($result, $id, $row ? (bool) ($row->is_public ?? false) : false);

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', 'Đã lưu và cập nhật giá.');
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

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderInventoryBuffPriceCell(array $result): string
    {
        $totalCny = (float) ($result['total_cny'] ?? 0);
        if ($totalCny <= 0) {
            return '<span class="text-warning">Chưa có giá</span>';
        }

        $converted = view('partials.price-converted', ['cny' => $totalCny])->render();

        return '<span class="text-success">'.$converted.'</span><br><small class="text-muted">¥'
            .number_format($totalCny, 2).'</small>';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderInventoryEmpirePriceCell(array $result): string
    {
        if (! Cs2PriceFeatures::empireEnabled()) {
            return '';
        }

        $totalCny = (float) ($result['total_empire_cny'] ?? 0);
        if ($totalCny <= 0) {
            return '<span class="text-muted small">Chưa có / chưa sync</span>';
        }

        $converted = view('partials.price-converted', ['cny' => $totalCny])->render();

        return '<span class="text-warning">'.$converted.'</span><br><small class="text-muted">≈ ¥'
            .number_format($totalCny, 2).'</small>';
    }
}
