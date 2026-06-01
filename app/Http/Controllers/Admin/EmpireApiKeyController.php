<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmpireApiKeyStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EmpireApiKeyController extends Controller
{
    public function __construct(
        private EmpireApiKeyStore $store,
    ) {}

    public function create(): View
    {
        return view('admin.empire-keys.form', [
            'key' => null,
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

    public function destroy(int $empireKey): RedirectResponse
    {
        $this->store->delete($empireKey);

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã xóa API key Empire.');
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
