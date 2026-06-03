<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanOrder;
use App\Services\PlanOrderActivator;
use App\Support\SubscriptionPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanOrderController extends Controller
{
    public function __construct(
        private PlanOrderActivator $activator,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', PlanOrder::STATUS_PENDING);
        if (! in_array($status, [PlanOrder::STATUS_PENDING, PlanOrder::STATUS_CONFIRMED, PlanOrder::STATUS_CANCELLED, 'all'], true)) {
            $status = PlanOrder::STATUS_PENDING;
        }

        $query = PlanOrder::query()
            ->with('user')
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('admin.plan-orders.index', [
            'orders' => $orders,
            'status' => $status,
            'pendingCount' => PlanOrder::query()->where('status', PlanOrder::STATUS_PENDING)->count(),
        ]);
    }

    public function confirm(PlanOrder $planOrder): RedirectResponse
    {
        if ($planOrder->status !== PlanOrder::STATUS_PENDING) {
            return redirect()->route('admin.plan-orders.index')
                ->with('error', 'Đơn này không còn ở trạng thái chờ duyệt.');
        }

        $this->activator->confirm($planOrder);

        $planName = SubscriptionPlans::get($planOrder->plan)['name'] ?? $planOrder->plan;

        return redirect()->route('admin.plan-orders.index', ['status' => PlanOrder::STATUS_PENDING])
            ->with('success', "Đã duyệt đơn #{$planOrder->id} — gán gói {$planName}, +{$planOrder->months} tháng cho {$planOrder->user?->email}.");
    }

    public function cancel(PlanOrder $planOrder): RedirectResponse
    {
        if ($planOrder->status !== PlanOrder::STATUS_PENDING) {
            return redirect()->route('admin.plan-orders.index')
                ->with('error', 'Chỉ hủy được đơn đang chờ duyệt.');
        }

        $planOrder->update(['status' => PlanOrder::STATUS_CANCELLED]);

        return redirect()->route('admin.plan-orders.index', ['status' => PlanOrder::STATUS_PENDING])
            ->with('success', "Đã hủy đơn #{$planOrder->id}.");
    }
}
