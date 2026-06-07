<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryPriceChecker;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Services\ItemImageService;
use App\Support\AdminFacingError;
use App\Support\Buff163AccountPool;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\InventoryResultPersister;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryDisplay;
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
        $inventories = $this->store->forAdmin($this->adminUsername())->map(function (object $inv) {
            $items = EmpireItemEnricher::enrich(
                InventorySnapshotReader::itemsFromInventory($inv),
                fetchMissing: false,
            );
            $inv->display_items = $this->priceHistory->enrichItems($items);
            $images = app(ItemImageService::class);
            $inv->display_items = array_map(
                fn (array $item) => $images->enrichItemRowForDisplay($item),
                $inv->display_items,
            );
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

        try {
            $row = $this->store->upsertForAdmin($this->adminUsername(), [
                'label' => $validated['label'],
                'notes' => $validated['notes'] ?? null,
                'url' => $validated['url'],
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'trade_at' => $this->parseTradeAtFromRequest($request),
            ]);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        }

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
        }

        return $this->runSnapshotAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
    }

    public function edit(int $inventory): View|RedirectResponse
    {
        $row = $this->store->findForAdmin($inventory, $this->adminUsername());
        if (! $row) {
            return redirect()->route('admin.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        return view('admin.inventories.form', [
            'inventory' => $row,
        ]);
    }

    public function update(Request $request, int $inventory, InventoryPriceChecker $checker): RedirectResponse
    {
        $row = $this->store->findForAdmin($inventory, $this->adminUsername());
        if (! $row) {
            return redirect()->route('admin.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        $validated = $this->validateForm($request, $inventory);

        try {
            $this->store->upsertForAdmin($this->adminUsername(), [
                'label' => $validated['label'],
                'notes' => $validated['notes'] ?? null,
                'url' => $validated['url'],
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'trade_at' => $this->parseTradeAtFromRequest($request),
            ], $inventory);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        }

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
        }

        return $this->runSnapshotAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
    }

    public function destroy(int $inventory): RedirectResponse
    {
        if (! $this->store->deleteForAdmin($inventory, $this->adminUsername())) {
            return redirect()->route('admin.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        return redirect()
            ->route('admin.inventories.index')
            ->with('success', 'Đã xóa kho khỏi danh sách.');
    }

    public function refresh(Request $request, int $inventory, InventoryPriceChecker $checker): JsonResponse|RedirectResponse
    {
        $row = $this->store->findForAdmin($inventory, $this->adminUsername());
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho.'], 404);
        }

        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl(
                $row->url,
                $row->label ?? null,
                refreshSteam: true,
                empireMode: 'admin',
                forceFreshPrices: true,
            );
            $this->persister->persist($result, $inventory, (bool) ($row->is_public ?? false));
            $row = $this->store->findForAdmin($inventory, $this->adminUsername());

            if ($request->wantsJson()) {
                $empireNote = '';
                if (Cs2PriceFeatures::empireEnabled()) {
                    $empireCount = (int) ($result['empire_priced_count'] ?? 0);
                    $empireNote = ', Empire: '.$empireCount.' skin';
                    if ($empireCount === 0) {
                        $empireNote .= ' (kiểm tra API key Empire / EMPIRE_ENABLED)';
                    }
                }

                $syncMessage = ! empty($result['inventory_empty'])
                    ? 'Đã cập nhật — kho hiện chưa có item.'
                    : sprintf(
                        'Đã cập nhật — %d/%d skin có giá Buff%s.',
                        (int) $result['priced_count'],
                        (int) $result['item_count'],
                        $empireNote
                    );

                return response()->json([
                    'ok' => true,
                    'message' => $syncMessage,
                    'inventory_id' => $inventory,
                    'item_count' => (int) $result['item_count'],
                    'inventory_empty' => ! empty($result['inventory_empty']),
                    'item_count_label' => ! empty($result['inventory_empty'])
                        ? 'Kho hiện chưa có item'
                        : (string) (int) $result['item_count'],
                    'priced_count' => (int) $result['priced_count'],
                    'empire_priced_count' => (int) ($result['empire_priced_count'] ?? 0),
                    'total_cny' => (float) $result['total_cny'],
                    'total_empire_cny' => (float) ($result['total_empire_cny'] ?? 0),
                    'last_checked_at' => Carbon::now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'),
                    'buff_price_html' => $this->renderInventoryBuffPriceCell($result),
                    'empire_price_html' => $this->renderInventoryEmpirePriceCell($result),
                    'identity_html' => $row ? InventoryDisplay::listIdentityHtml($row) : '',
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

            $message = AdminFacingError::message($e, 'Lỗi server khi check giá — xem storage/logs/laravel.log');

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 500);
            }

            return back()->with('error', $message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $exceptInventoryId = null): array
    {
        $adminUsername = $this->adminUsername();

        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'url' => [
                'required',
                'url',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) use ($adminUsername, $exceptInventoryId): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if ($this->store->hasDuplicateForAdmin($adminUsername, $value, $exceptInventoryId)) {
                        $fail('Kho Steam này đã có trong danh sách — không thêm trùng.');
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'trade_at_date' => ['nullable', 'date'],
            'trade_at_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'trade_at_minute' => ['nullable', 'integer', 'min:0', 'max:59'],
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

        try {
            return Carbon::createFromFormat(
                'Y-m-d H:i',
                sprintf('%s %02d:%02d', $date, $hour, $minute),
                'Asia/Ho_Chi_Minh'
            )->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function runCheckAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl($url, $label, refreshSteam: true, empireMode: 'admin', forceFreshPrices: true);
            $row = $this->store->findForAdmin($id, $this->adminUsername());
            $this->persister->persist($result, $id, $row ? (bool) ($row->is_public ?? false) : false);

            $message = ! empty($result['inventory_empty'])
                ? 'Đã lưu — kho hiện chưa có item.'
                : 'Đã lưu và cập nhật giá.';

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', $message);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.inventories.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.inventories.index')
                ->with('error', AdminFacingError::message($e, 'Lỗi khi check giá — xem storage/logs/laravel.log'));
        }
    }

    private function runSnapshotAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $this->extendExecutionTime();

        try {
            $result = $checker->loadInventorySnapshot($url, $label, refreshSteam: true);
            $row = $this->store->findForAdmin($id, $this->adminUsername());
            $this->persister->persist($result, $id, $row ? (bool) ($row->is_public ?? false) : false);

            $message = ! empty($result['inventory_empty'])
                ? 'Đã lưu — kho hiện chưa có item.'
                : sprintf('Đã lưu — %d skin (chưa check giá Buff).', (int) $result['item_count']);

            return redirect()
                ->route('admin.inventories.index')
                ->with('success', $message);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.inventories.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.inventories.index')
                ->with('error', AdminFacingError::message($e, 'Lỗi khi tải kho — xem storage/logs/laravel.log'));
        }
    }

    private function adminUsername(): string
    {
        return (string) session('admin_username', config('admin.username'));
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
