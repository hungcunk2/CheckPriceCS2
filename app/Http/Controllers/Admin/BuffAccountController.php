<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Buff163HealthService;
use App\Services\BuffAccountStore;
use App\Services\EmpireApiKeyStore;
use App\Support\CsgoEmpireApiPool;
use App\Services\CsgoEmpireHealthService;
use App\Services\CsTradeHealthService;
use App\Support\Buff163AccountPool;
use App\Support\ExchangeRateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BuffAccountController extends Controller
{
    public function __construct(
        private Buff163HealthService $health,
        private CsTradeHealthService $csTradeHealth,
        private CsgoEmpireHealthService $empireHealth,
        private BuffAccountStore $store,
        private EmpireApiKeyStore $empireKeyStore,
    ) {}

    public function index(): View
    {
        return view('admin.buff-accounts.index', [
            'usesDatabase' => Buff163AccountPool::usesDatabase(),
            'configured' => Buff163AccountPool::isConfigured(),
            'accounts' => $this->health->accountsOverview(),
            'managedAccounts' => Buff163AccountPool::usesDatabase() ? $this->store->all() : collect(),
            'csTrade' => $this->csTradeHealth->overview(),
            'empire' => $this->empireHealth->overview(),
            'exchangeRates' => ExchangeRateStore::get(),
            'empireUsesDatabase' => CsgoEmpireApiPool::usesDatabase(),
            'empireKeys' => CsgoEmpireApiPool::usesDatabase()
                ? $this->empireKeyStore->all()->map(function (object $ek) {
                    $ek->last_check = $this->empireHealth->lastCheckForLabel($ek->label);
                    $ek->cooldown_seconds = CsgoEmpireApiPool::cooldownRemaining($ek->label);

                    return $ek;
                })
                : collect(),
        ]);
    }

    public function updateExchangeRates(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cny_to_vnd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'vnd_to_usd' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'empire_coin_to_usd' => ['required', 'numeric', 'min:0.0001', 'max:100'],
        ]);

        try {
            ExchangeRateStore::save($validated);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->to(route('admin.buff-accounts.index').'#exchange-rates')
                ->withInput()
                ->with('error', str_contains($e->getMessage(), 'exchange_rates')
                    ? 'Chưa có bảng tỷ giá — chạy trên VPS: php artisan migrate --force'
                    : 'Không lưu được tỷ giá: '.$e->getMessage());
        }

        return redirect()
            ->to(route('admin.buff-accounts.index').'#exchange-rates')
            ->with('success', 'Đã lưu tỷ giá quy đổi.');
    }

    public function create(): View
    {
        return view('admin.buff-accounts.form', [
            'account' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        try {
            $this->store->upsert($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã thêm acc Buff.');
    }

    public function edit(int $buffAccount): View|RedirectResponse
    {
        $account = $this->store->find($buffAccount);
        if (! $account) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy acc.');
        }

        return view('admin.buff-accounts.form', [
            'account' => $account,
        ]);
    }

    public function update(Request $request, int $buffAccount): RedirectResponse
    {
        $account = $this->store->find($buffAccount);
        if (! $account) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy acc.');
        }

        $validated = $this->validateForm($request, $buffAccount);

        try {
            $this->store->upsert($validated, $buffAccount);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã cập nhật acc '.$account->label.'.');
    }

    public function destroy(int $buffAccount): RedirectResponse
    {
        $this->store->delete($buffAccount);

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã xóa acc Buff.');
    }

    public function importFromEnv(): RedirectResponse
    {
        $count = $this->store->importFromEnvIfEmpty();

        if ($count === 0) {
            return redirect()
                ->route('admin.buff-accounts.index')
                ->with('error', 'Đã có acc trong DB hoặc .env trống — không import được.');
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', "Đã import {$count} acc từ .env vào DB.");
    }

    public function probeAll(): RedirectResponse
    {
        $messages = [];

        $csResult = $this->csTradeHealth->probe();
        $messages[] = 'cs.trade: '.$csResult['message'];

        if (Buff163AccountPool::isConfigured()) {
            $this->health->probeAll();
            $messages[] = 'Buff: đã kiểm tra tất cả acc.';
        } else {
            $messages[] = 'Buff: chưa có acc — bỏ qua.';
        }

        $empireResult = $this->empireHealth->probe();
        $messages[] = 'Empire: '.($empireResult['message'] ?? '—');

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', implode(' ', $messages));
    }

    public function probeCsTrade(): RedirectResponse
    {
        $result = $this->csTradeHealth->probe();

        $type = ($result['status'] ?? '') === 'ok' ? 'success' : 'error';

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with($type, 'cs.trade: '.($result['message'] ?? '—'));
    }

    public function probeEmpire(): RedirectResponse
    {
        $result = $this->empireHealth->probe();

        $status = $result['status'] ?? '';
        $type = $status === 'ok' ? 'success' : ($status === 'warning' ? 'warning' : 'error');

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with($type, 'Empire: '.($result['message'] ?? '—'));
    }

    public function probe(Request $request, string $label): JsonResponse|RedirectResponse
    {
        if (! Buff163AccountPool::isConfigured()) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Chưa có acc Buff.'], 422);
            }

            return redirect()
                ->route('admin.buff-accounts.index')
                ->with('error', 'Chưa có acc Buff nào.');
        }

        $result = $this->health->probe($label);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'result' => $result]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Acc '.$label.': '.$result['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $ignoreId = null): array
    {
        $labelRule = ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9\-]+$/'];

        if ($ignoreId) {
            $labelRule[] = 'unique:buff_accounts,label,'.$ignoreId;
        } else {
            $labelRule[] = 'unique:buff_accounts,label';
        }

        $sessionRule = $ignoreId
            ? ['nullable', 'string', 'max:8000']
            : ['required', 'string', 'max:8000'];

        $validated = $request->validate([
            'label' => $labelRule,
            'session' => $sessionRule,
            'csrf_token' => ['nullable', 'string', 'max:512'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
