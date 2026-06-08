<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ItemImageService;
use App\Services\InventorySyncService;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Support\AdminFacingError;
use App\Support\Buff163AccountPool;
use App\Support\InventoryDisplay;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\InventoryRefreshLimiter;
use App\Support\InventoryResultPersister;
use App\Support\InventorySnapshotReader;
use App\Support\InventorySyncDispatch;
use App\Support\InventorySyncStatus;
use App\Support\InventoryWeaponStats;
use App\Support\MemberInventorySchema;
use App\Support\SubscriptionPlans;
use App\Support\SubscriptionSyncPolicy;
use Carbon\Carbon;
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
        private InventoryRefreshLimiter $refreshLimiter,
    ) {}

    public function index(): View
    {
        if (! MemberInventorySchema::isReady()) {
            return view('member.inventories.setup-required');
        }

        $user = $this->requireUser();

        try {
            $inventories = $this->mapInventories($this->store->forUser($user->id));
        } catch (\Throwable $e) {
            report($e);
            $inventories = collect();
            session()->flash('error', AdminFacingError::message($e, 'Không tải được danh sách kho.'));
        }

        $limit = $user->inventorySlotLimit();
        $used = $this->store->countForUser($user->id);

        return view('member.inventories.index', [
            'inventories' => $inventories,
            'buffConfigured' => Buff163AccountPool::isConfigured(),
            'empireEnabled' => Cs2PriceFeatures::empireEnabled(),
            'slotLimit' => $limit,
            'slotUsed' => $used,
            'planLabel' => $user->subscriptionPlanLabel() ?? 'Pro',
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (! MemberInventorySchema::isReady()) {
            return redirect()->route('member.inventories.index');
        }

        $user = $this->requireUser();

        if (! $this->canAddInventory($user)) {
            return redirect()
                ->route('member.inventories.index')
                ->with('error', $this->slotLimitMessage($user));
        }

        return view('member.inventories.form', ['inventory' => null]);
    }

    public function store(Request $request, InventoryPriceChecker $checker): RedirectResponse
    {
        if (! MemberInventorySchema::isReady()) {
            return redirect()->route('member.inventories.index');
        }

        $user = $this->requireUser();

        if (! $this->canAddInventory($user)) {
            return back()->with('error', $this->slotLimitMessage($user));
        }

        $validated = $this->validateForm($request, user: $user);

        try {
            $row = $this->store->upsertForUser($user->id, [
                'label' => $validated['label'],
                'notes' => $validated['notes'] ?? null,
                'url' => $validated['url'],
                'trade_at' => $this->parseTradeAtFromRequest($request),
            ]);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', AdminFacingError::message($e, 'Không lưu được kho — xem storage/logs/laravel.log'));
        }

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
        }

        return $this->runSnapshotAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
    }

    public function edit(int $inventory): View|RedirectResponse
    {
        $row = $this->findOwned($inventory);
        if (! $row) {
            return redirect()->route('member.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        return view('member.inventories.form', ['inventory' => $row]);
    }

    public function update(Request $request, int $inventory, InventoryPriceChecker $checker): RedirectResponse
    {
        if (! $this->findOwned($inventory)) {
            return redirect()->route('member.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        $validated = $this->validateForm($request, exceptInventoryId: $inventory, user: $this->requireUser());

        try {
            $this->store->upsertForUser($this->requireUser()->id, [
                'label' => $validated['label'],
                'notes' => $validated['notes'] ?? null,
                'url' => $validated['url'],
                'trade_at' => $this->parseTradeAtFromRequest($request),
            ], $inventory);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', AdminFacingError::message($e, 'Không lưu được kho — xem storage/logs/laravel.log'));
        }

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
        }

        return $this->runSnapshotAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
    }

    public function destroy(int $inventory): RedirectResponse
    {
        if (! $this->findOwned($inventory)) {
            return redirect()->route('member.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        $this->store->deleteForUser($inventory, $this->requireUser()->id);

        return redirect()
            ->route('member.inventories.index')
            ->with('success', 'Đã xóa kho khỏi danh sách.');
    }

    public function refresh(Request $request, int $inventory, InventorySyncService $sync): JsonResponse|RedirectResponse
    {
        $row = $this->findOwned($inventory);
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho.'], 404);
        }

        $user = $this->requireUser();
        if (! $this->refreshLimiter->canRefresh($user)) {
            $message = $this->refreshLimiter->limitExceededMessage($user);

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 429);
            }

            return back()->with('error', $message);
        }

        if ($request->wantsJson() && InventorySyncDispatch::shouldQueue()) {
            InventorySyncDispatch::dispatch([$inventory], isManualRefresh: true, memberUserId: $user->id);

            return response()->json([
                'ok' => true,
                'queued' => true,
                'inventory_id' => $inventory,
                'message' => 'Đang đồng bộ nền — vui lòng đợi vài giây…',
            ]);
        }

        $this->extendExecutionTime();

        try {
            $syncResult = $sync->syncByInventoryIds(
                [$inventory],
                isManualRefresh: true,
                memberUserIdForLimiter: $user->id,
            );
            if ($syncResult['failed'] > 0) {
                throw new RuntimeException($syncResult['messages'][0] ?? 'Đồng bộ thất bại.');
            }

            $row = $this->findOwned($inventory);

            if ($request->wantsJson()) {
                return response()->json($this->refreshJsonFromRow($row));
            }

            return redirect()
                ->route('member.inventories.index')
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

    public function syncStatus(int $inventory): JsonResponse
    {
        $row = $this->findOwned($inventory);
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho.'], 404);
        }

        $status = InventorySyncStatus::get($inventory);
        if ($status === null) {
            return response()->json(['ok' => true, 'status' => 'idle']);
        }

        if (($status['status'] ?? '') === 'done') {
            $row = $this->findOwned($inventory);

            return response()->json(array_merge($this->refreshJsonFromRow($row), [
                'status' => 'done',
            ]));
        }

        if (($status['status'] ?? '') === 'failed') {
            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'message' => $status['message'] ?? 'Đồng bộ thất bại.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'status' => $status['status'] ?? 'running',
            'message' => $status['message'] ?? 'Đang đồng bộ…',
        ]);
    }

    private function requireUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403, 'Cần đăng nhập.');
        }

        return $user;
    }

    private function findOwned(int $id): ?object
    {
        if (! MemberInventorySchema::isReady()) {
            return null;
        }

        $userId = auth()->id();
        if ($userId === null) {
            return null;
        }

        return $this->store->findForUser($id, $userId);
    }

    private function canAddInventory(User $user): bool
    {
        $limit = $user->inventorySlotLimit();
        if ($limit === null) {
            return true;
        }

        return $this->store->countForUser($user->id) < $limit;
    }

    private function slotLimitMessage(User $user): string
    {
        $limit = $user->inventorySlotLimit();
        $plan = $user->subscriptionPlanLabel() ?? 'gói hiện tại';
        $slots = SubscriptionPlans::get($user->subscription_plan ?? 'pro')['slots'] ?? ($limit.' kho');

        return "Gói {$plan} chỉ cho phép {$slots}. Nâng cấp tại trang bảng giá.";
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $inventories
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mapInventories($inventories)
    {
        return $inventories->map(function (object $inv) {
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
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $exceptInventoryId = null, ?User $user = null): array
    {
        $user ??= $this->requireUser();
        $userId = $user->id;

        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'url' => [
                'required',
                'url',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) use ($userId, $exceptInventoryId): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if ($this->store->hasDuplicateForUser($userId, $value, $exceptInventoryId)) {
                        $fail('Kho Steam này đã có trong danh sách — không thêm trùng.');
                    }
                },
            ],
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
            $parsed = Carbon::createFromFormat(
                'Y-m-d H:i',
                sprintf('%s %02d:%02d', $date, $hour, $minute),
                'Asia/Ho_Chi_Minh'
            );

            return $parsed instanceof Carbon ? $parsed->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function runCheckAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $user = $this->requireUser();
        if (! $this->refreshLimiter->canRefresh($user)) {
            return back()->with('error', $this->refreshLimiter->limitExceededMessage($user));
        }

        $this->extendExecutionTime();

        $forceFresh = SubscriptionSyncPolicy::requiresFreshSync($user->subscription_plan);

        try {
            $result = $checker->checkUrl($url, $label, refreshSteam: true, empireMode: 'member', forceFreshPrices: $forceFresh);
            $row = $this->findOwned($id);

            $this->persister->persistForUser($result, $user->id, $id, $row ? (bool) ($row->is_public ?? false) : false);
            $this->refreshLimiter->record($user);

            $message = ! empty($result['inventory_empty'])
                ? 'Đã lưu — kho hiện chưa có item.'
                : 'Đã lưu và cập nhật giá.';

            return redirect()
                ->route('member.inventories.index')
                ->with('success', $message);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('member.inventories.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('member.inventories.index')
                ->with('error', AdminFacingError::message($e, 'Lỗi khi check giá — xem storage/logs/laravel.log'));
        }
    }

    private function runSnapshotAndRedirect(int $id, string $url, string $label, InventoryPriceChecker $checker): RedirectResponse
    {
        $this->extendExecutionTime();

        try {
            $result = $checker->loadInventorySnapshot($url, $label, refreshSteam: true);
            $row = $this->findOwned($id);

            $this->persister->persistForUser($result, $this->requireUser()->id, $id, $row ? (bool) ($row->is_public ?? false) : false);

            $message = ! empty($result['inventory_empty'])
                ? 'Đã lưu — kho hiện chưa có item.'
                : sprintf('Đã lưu — %d skin (chưa check giá Buff).', (int) $result['item_count']);

            return redirect()
                ->route('member.inventories.index')
                ->with('success', $message);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('member.inventories.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('member.inventories.index')
                ->with('error', AdminFacingError::message($e, 'Lỗi khi tải kho — xem storage/logs/laravel.log'));
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
     * @return array<string, mixed>
     */
    private function refreshJsonFromRow(?object $row): array
    {
        if (! $row) {
            return ['ok' => false, 'message' => 'Không tìm thấy kho.'];
        }

        $result = $this->resultArrayFromRow($row);
        $empireNote = '';
        if (Cs2PriceFeatures::empireEnabled()) {
            $empireCount = (int) ($result['empire_priced_count'] ?? 0);
            $empireNote = ', Empire: '.$empireCount.' skin';
        }

        $syncMessage = ! empty($result['inventory_empty'])
            ? 'Đã cập nhật — kho hiện chưa có item.'
            : sprintf(
                'Đã cập nhật — %d/%d skin có giá Buff%s.',
                (int) $result['priced_count'],
                (int) $result['item_count'],
                $empireNote
            );

        return [
            'ok' => true,
            'message' => $syncMessage,
            'inventory_id' => (int) $row->id,
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
            'identity_html' => InventoryDisplay::listIdentityHtml($row),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultArrayFromRow(object $row): array
    {
        $snapshot = is_array($row->last_snapshot ?? null)
            ? $row->last_snapshot
            : (array) json_decode(json_encode($row->last_snapshot ?? []), true);

        return [
            'total_cny' => (float) ($row->last_total_cny ?? 0),
            'total_empire_cny' => (float) ($snapshot['total_empire_cny'] ?? 0),
            'item_count' => (int) ($row->item_count ?? 0),
            'priced_count' => (int) ($row->priced_count ?? 0),
            'empire_priced_count' => (int) ($snapshot['empire_priced_count'] ?? 0),
            'inventory_empty' => (bool) ($snapshot['inventory_empty'] ?? false),
        ];
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
