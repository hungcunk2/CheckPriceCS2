<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Models\PlanOrder;
use App\Models\User;
use App\Support\SubscriptionPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request): View
    {
        $plan = strtolower((string) $request->query('plan', 'max'));
        if (! SubscriptionPlans::exists($plan)) {
            $plan = 'max';
        }

        $months = (int) $request->query('months', 3);
        if (! in_array($months, SubscriptionPlans::CYCLES, true)) {
            $months = 3;
        }

        $planData = SubscriptionPlans::get($plan);
        $user = $this->resolveUser($request);
        $paymentSettings = PaymentSetting::current();
        $qrImageUrl = ($paymentSettings->isConfigured() && $user !== null)
            ? $this->qrProxyUrl($plan, $months, $user->email)
            : null;

        $plansJson = [];
        foreach (SubscriptionPlans::PLANS as $key => $p) {
            $plansJson[$key] = [
                'name' => $p['name'],
                'monthly' => $p['prices'][1],
                'slots' => $p['slots'],
                'prices' => $p['prices'],
            ];
        }

        return view('public.checkout', [
            'planKey' => $plan,
            'plan' => $planData,
            'months' => $months,
            'plansJson' => $plansJson,
            'checkoutUser' => $user,
            'payment' => $paymentSettings->forCheckout(),
            'qrImageUrl' => $qrImageUrl,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(SubscriptionPlans::PLANS))],
            'months' => ['required', 'integer', 'in:'.implode(',', SubscriptionPlans::CYCLES)],
            'email' => ['required', 'email', 'max:255'],
            'payment_method' => ['required', 'string', 'in:bank'],
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

    public function qrImage(Request $request): Response
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(SubscriptionPlans::PLANS))],
            'months' => ['required', 'integer', 'in:'.implode(',', SubscriptionPlans::CYCLES)],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $plan = $validated['plan'];
        $months = (int) $validated['months'];
        $email = mb_strtolower(trim($validated['email']));

        $settings = PaymentSetting::current();
        if (! $settings->isConfigured()) {
            abort(404);
        }

        $amount = SubscriptionPlans::price($plan, $months);
        $reference = SubscriptionPlans::transferReference($email, $plan, $months);
        $vietqrUrl = $settings->quickLinkImageUrl($amount, $reference);
        if ($vietqrUrl === null) {
            abort(404);
        }

        $png = Cache::remember(
            $settings->orderQrCacheKey($amount, $reference),
            60 * 60 * 24 * 7,
            function () use ($vietqrUrl) {
                $response = Http::timeout(20)->get($vietqrUrl);

                return $response->successful() ? $response->body() : null;
            }
        );

        if (! is_string($png) || $png === '') {
            abort(502);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function qrProxyUrl(string $plan, int $months, string $email): string
    {
        return route('public.checkout.qr', [
            'plan' => $plan,
            'months' => $months,
            'email' => $email,
        ]);
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
