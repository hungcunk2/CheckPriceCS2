<?php

namespace App\Http\Controllers;

use App\Models\PlanOrder;
use App\Models\User;
use App\Support\SubscriptionPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $plan = strtolower((string) $request->query('plan', ''));
        if (! SubscriptionPlans::exists($plan)) {
            return redirect()->route('public.pricing')
                ->with('error', 'Gói không hợp lệ. Vui lòng chọn lại từ bảng giá.');
        }

        $months = (int) $request->query('months', 1);
        if (! in_array($months, SubscriptionPlans::CYCLES, true)) {
            $months = 1;
        }

        $planData = SubscriptionPlans::get($plan);
        $amount = SubscriptionPlans::price($plan, $months);
        $user = $this->resolveUser($request);
        $reference = $user
            ? SubscriptionPlans::transferReference($user->email, $plan, $months)
            : null;

        return view('public.checkout', [
            'planKey' => $plan,
            'plan' => $planData,
            'months' => $months,
            'amount' => $amount,
            'reference' => $reference,
            'checkoutUser' => $user,
            'payment' => config('cs2price.payment'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(SubscriptionPlans::PLANS))],
            'months' => ['required', 'integer', 'in:'.implode(',', SubscriptionPlans::CYCLES)],
            'email' => ['required', 'email', 'max:255'],
            'member_note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();
        if ($user === null) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Không tìm thấy tài khoản với email này. Đăng ký trước rồi thanh toán.']);
        }

        $plan = $validated['plan'];
        $months = (int) $validated['months'];
        $amount = SubscriptionPlans::price($plan, $months);
        $reference = SubscriptionPlans::transferReference($user->email, $plan, $months);

        $hasPending = PlanOrder::query()
            ->where('user_id', $user->id)
            ->where('reference', $reference)
            ->where('status', PlanOrder::STATUS_PENDING)
            ->exists();

        if (! $hasPending) {
            PlanOrder::query()->create([
                'user_id' => $user->id,
                'plan' => $plan,
                'months' => $months,
                'amount_vnd' => $amount,
                'reference' => $reference,
                'status' => PlanOrder::STATUS_PENDING,
                'member_note' => $validated['member_note'] ?? null,
            ]);
        }

        return redirect()
            ->route('public.checkout', ['plan' => $plan, 'months' => $months, 'email' => $user->email])
            ->with('success', 'Đã ghi nhận yêu cầu thanh toán. Admin sẽ kích hoạt gói trong vòng 24h sau khi xác nhận chuyển khoản.');
    }

    private function resolveUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $email = mb_strtolower(trim((string) $request->query('email', '')));
        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }
}
