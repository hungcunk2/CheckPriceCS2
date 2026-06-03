<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cs2CapCatalogService;
use App\Services\InventoryPriceChecker;
use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Support\Buff163AccountPool;
use App\Support\Cs2PriceFeatures;
use App\Support\EmpireItemEnricher;
use App\Support\InventoryResultPersister;
use App\Support\InventorySnapshotReader;
use App\Support\InventoryWeaponStats;
use App\Support\SubscriptionPlans;
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
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        assert($user instanceof User);

        $inventories = $this->mapInventories($this->store->forUser($user->id));
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
        $user = auth()->user();
        assert($user instanceof User);

        if (! $this->canAddInventory($user)) {
            return redirect()
                ->route('member.inventories.index')
                ->with('error', $this->slotLimitMessage($user));
        }

        return view('member.inventories.form', ['inventory' => null]);
    }

    public function store(Request $request, InventoryPriceChecker $checker): RedirectResponse
    {
        $user = auth()->user();
        assert($user instanceof User);

        if (! $this->canAddInventory($user)) {
            return back()->with('error', $this->slotLimitMessage($user));
        }

        $validated = $this->validateForm($request);

        $row = $this->store->upsertForUser($user->id, [
            'label' => $validated['label'],
            'url' => $validated['url'],
            'is_public' => $request->boolean('is_public', false),
            'trade_at' => $this->parseTradeAtFromRequest($request),
        ]);

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect((int) $row->id, $validated['url'], $validated['label'], $checker);
        }

        return redirect()
            ->route('member.inventories.index')
            ->with('success', 'Đã thêm kho đồ.');
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

        $validated = $this->validateForm($request);

        $user = auth()->user();
        assert($user instanceof User);

        $this->store->upsertForUser($user->id, [
            'label' => $validated['label'],
            'url' => $validated['url'],
            'is_public' => $request->boolean('is_public'),
            'trade_at' => $this->parseTradeAtFromRequest($request),
        ], $inventory);

        if ($request->boolean('check_now')) {
            return $this->runCheckAndRedirect($inventory, $validated['url'], $validated['label'], $checker);
        }

        return redirect()
            ->route('member.inventories.index')
            ->with('success', 'Đã cập nhật kho đồ.');
    }

    public function destroy(int $inventory): RedirectResponse
    {
        if (! $this->findOwned($inventory)) {
            return redirect()->route('member.inventories.index')->with('error', 'Không tìm thấy kho.');
        }

        $user = auth()->user();
        assert($user instanceof User);

        $this->store->deleteForUser($inventory, $user->id);

        return redirect()
            ->route('member.inventories.index')
            ->with('success', 'Đã xóa kho khỏi danh sách.');
    }

    public function refresh(Request $request, int $inventory, InventoryPriceChecker $checker): JsonResponse|RedirectResponse
    {
        $row = $this->findOwned($inventory);
        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy kho.'], 404);
        }

        $this->extendExecutionTime();

        try {
            $result = $checker->checkUrl(
                $row->url,
                $row->label ?? null,
                refreshSteam: true,
                empireMode: 'member',
            );
            $user = auth()->user();
            assert($user instanceof User);

            $this->persister->persistForUser($result, $user->id, $inventory, (bool) ($row->is_public ?? false));

            if ($request->wantsJson()) {
                $empireNote = '';
                if (Cs2PriceFeatures::empireEnabled()) {
                    $empireCount = (int) ($result['empire_priced_count'] ?? 0);
                    $empireNote = ', Empire: '.$empireCount.' skin';
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
                ->route('member.inventories.index')
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

    private function findOwned(int $id): ?object
    {
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
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2000'],
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
            $result = $checker->checkUrl($url, $label, refreshSteam: true, empireMode: 'member');
            $row = $this->findOwned($id);
            $user = auth()->user();
            assert($user instanceof User);

            $this->persister->persistForUser($result, $user->id, $id, $row ? (bool) ($row->is_public ?? false) : false);

            return redirect()
                ->route('member.inventories.index')
                ->with('success', 'Đã lưu và cập nhật giá.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('member.inventories.edit', $id)
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
