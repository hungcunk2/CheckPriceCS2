<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SubscriptionPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderByDesc('id')->paginate(30);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);
        User::query()->create($validated);

        return redirect()->route('admin.users.index')->with('success', 'Đã tạo user.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateForm($request, $user);
        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }
        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật user.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Đã xóa user.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?User $user = null): array
    {
        $emailRule = ['required', 'email', 'max:255'];
        $emailRule[] = $user
            ? 'unique:users,email,'.$user->id
            : 'unique:users,email';

        $passwordRule = $user
            ? ['nullable', 'string', 'min:8', 'max:128']
            : ['required', 'string', 'min:8', 'max:128'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => $emailRule,
            'password' => $passwordRule,
            'is_active' => ['sometimes', 'boolean'],
            'paid_until' => ['nullable', 'date'],
            'subscription_plan' => ['nullable', 'string', 'in:'.implode(',', array_keys(SubscriptionPlans::PLANS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        if (($validated['subscription_plan'] ?? '') === '') {
            $validated['subscription_plan'] = null;
        }

        return $validated;
    }
}
