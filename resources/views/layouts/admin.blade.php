<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.theme-init')
    @include('partials.currency-init')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $meta = \App\Support\SiteMeta::noindex(trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')).' — Admin' : 'Admin — '.config('site.name')); @endphp
    @include('partials.site-meta')
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/image-lightbox.css') }}">
    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="{{ asset('images/logo.png') }}" alt="CheckPrice CS2" class="site-logo">
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">QUẢN LÝ</div>
                    <ul class="nav-menu">
                        <li class="nav-item {{ request()->routeIs('admin.inventories.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.inventories.index') }}" class="nav-link">
                                <i class="fas fa-boxes"></i>
                                <span>Kho đồ Steam</span>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.blog.index') }}" class="nav-link">
                                <i class="fas fa-newspaper"></i>
                                <span>Blog</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('public.inventories') }}" class="nav-link" target="_blank">
                                <i class="fas fa-external-link-alt"></i>
                                <span>Xem trang công khai</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">HỆ THỐNG</div>
                    <ul class="nav-menu">
                        <li class="nav-item {{ request()->routeIs('admin.buff-accounts.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.buff-accounts.index') }}" class="nav-link">
                                <i class="fas fa-user-shield"></i>
                                <span>Buff & nguồn kho</span>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.payment-settings.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.payment-settings.edit') }}" class="nav-link">
                                <i class="fas fa-qrcode"></i>
                                <span>TK thanh toán</span>
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.plan-orders.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.plan-orders.index') }}" class="nav-link">
                                <i class="fas fa-receipt"></i>
                                <span>Đơn thanh toán</span>
                                @php
                                    $pendingPlanOrders = 0;
                                    if (\Illuminate\Support\Facades\Schema::hasTable('plan_orders')) {
                                        try {
                                            $pendingPlanOrders = (int) \App\Models\PlanOrder::query()->where('status', 'pending')->count();
                                        } catch (\Throwable) {
                                            $pendingPlanOrders = 0;
                                        }
                                    }
                                @endphp
                                @if($pendingPlanOrders > 0)
                                    <span class="badge rounded-pill text-bg-warning ms-1">{{ $pendingPlanOrders }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.users.index') }}" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span>User trả phí</span>
                            </a>
                        </li>
                        @php
                            $adminChatUnread = app(\App\Services\SupportChatService::class)->unreadCountForAdmin();
                        @endphp
                        <li class="nav-item {{ request()->routeIs('admin.support.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.support.index') }}" class="nav-link">
                                <i class="fas fa-comments"></i>
                                <span>Chat hỗ trợ</span>
                                @if($adminChatUnread > 0)
                                    <span class="badge rounded-pill text-bg-danger ms-1">{{ $adminChatUnread }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item {{ request()->routeIs('admin.empire-proxy.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.empire-proxy.edit') }}" class="nav-link">
                                <i class="fas fa-network-wired"></i>
                                <span>Proxy Empire</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Đăng xuất</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1 class="page-title mb-0">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="header-right d-flex align-items-center gap-3">
                    @include('partials.currency-switch')
                    <button
                        type="button"
                        class="btn btn-sm theme-toggle-btn"
                        aria-label="Bật giao diện tối"
                    >
                        <i class="fas fa-moon theme-icon-dark"></i>
                        <i class="fas fa-sun theme-icon-light d-none"></i>
                    </button>
                    <span class="user-name">{{ session('admin_username', 'Admin') }}</span>
                </div>
            </header>
            <main class="admin-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @yield('content')
            </main>
            @include('partials.footer', ['compact' => true])
        </div>
    </div>

    <div class="loading-overlay" id="admin-loading" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,.7);z-index:3000;align-items:center;justify-content:center;">
        <div class="text-center">
            <div class="spinner-border text-primary"></div>
            <div class="mt-2 text-muted">Đang check giá…</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/theme-toggle.js') }}"></script>
    <script src="{{ asset('js/currency-switch.js') }}"></script>
    <script src="{{ asset('js/image-lightbox.js') }}"></script>
    <script src="{{ asset('js/trade-countdown.js') }}"></script>
    <script>
        window.__cpcs2CatalogEndpoint = @json(route('api.guest.item-image'));
        window.__cpcs2PlaceholderImg = @json(asset('images/logo.png'));
    </script>
    <script src="{{ asset('js/item-image-fallback.js') }}"></script>
    @stack('scripts')
</body>
</html>
