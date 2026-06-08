<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CsgoEmpireHealthService;
use App\Services\EmpireApiKeyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EmpireApiKeyController extends Controller
{
    public function __construct(
        private EmpireApiKeyStore $store,
        private CsgoEmpireHealthService $empireHealth,
    ) {}

    public function create(): View
    {
        return view('admin.empire-keys.form', [
            'key' => null,
            'nextSortOrder' => $this->store->nextSortOrder(),
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
            ->with('success', 'Đã thêm API key Empire.');
    }

    public function edit(int $empireKey): View|RedirectResponse
    {
        $row = $this->store->find($empireKey);
        if (! $row) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        return view('admin.empire-keys.form', [
            'key' => $row,
            'nextSortOrder' => null,
        ]);
    }

    public function update(Request $request, int $empireKey): RedirectResponse
    {
        $row = $this->store->find($empireKey);
        if (! $row) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        $validated = $this->validateForm($request, $empireKey);

        try {
            $this->store->upsert($validated, $empireKey);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã cập nhật API key Empire.');
    }

    public function destroy(Request $request, int $empireKey): JsonResponse|RedirectResponse
    {
        $this->store->delete($empireKey);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Đã xóa API key Empire.',
                'empire_key_id' => $empireKey,
            ]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã xóa API key Empire.');
    }

    public function probe(Request $request, int $empireKey): JsonResponse|RedirectResponse
    {
        $row = $this->store->find($empireKey);
        if (! $row) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Không tìm thấy key.'], 404);
            }

            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        $result = $this->empireHealth->probeAccount([
            'label' => $row->label,
            'api_key' => (string) $row->api_key,
        ]);

        $http = isset($result['http_status']) ? ' HTTP '.$result['http_status'] : '';
        $message = $row->label.':'.$http.' — '.($result['message'] ?? '—');
        $ok = ($result['status'] ?? '') === 'ok';

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'result' => $result,
                'empire_key_id' => $empireKey,
            ]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with($ok ? 'success' : 'error', $message);
    }

    public function importFromEnv(): RedirectResponse
    {
        $count = $this->store->importFromEnvIfEmpty();

        if ($count === 0) {
            return redirect()
                ->route('admin.buff-accounts.index')
                ->with('error', 'Đã có key trong DB hoặc .env trống — không import được.');
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', "Đã import {$count} API key từ .env vào DB.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $ignoreId = null): array
    {
        $labelRule = ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9\-]+$/'];

        if ($ignoreId) {
            $labelRule[] = 'unique:empire_api_keys,label,'.$ignoreId;
        } else {
            $labelRule[] = 'unique:empire_api_keys,label';
        }

        $keyRule = $ignoreId
            ? ['nullable', 'string', 'max:512']
            : ['required', 'string', 'max:512'];

        $validated = $request->validate([
            'label' => $labelRule,
            'api_key' => $keyRule,
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
