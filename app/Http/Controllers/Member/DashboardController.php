<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\PricingTier;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        return view('member.dashboard', [
            'user' => $user,
            'tier' => PricingTier::Member,
            'meta' => \App\Support\SiteMeta::forRequest('Tài khoản'),
        ]);
    }
}
