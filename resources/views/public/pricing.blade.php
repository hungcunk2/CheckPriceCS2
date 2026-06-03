@extends('layouts.landing')

@section('content')
@include('landing.nav')
@include('partials.flash-alerts')

<section class="lp-pricing-page">
    <div class="lp-container">
        <div class="lp-pricing-header">
            <span class="lp-pricing-badge">Bảng giá</span>
            <h1 class="lp-pricing-title">Chọn gói phù hợp với bạn</h1>
            <p class="lp-pricing-subtitle">
                Từ cá nhân flip 1–2 acc đến shop lớn quản lý hàng trăm kho — luôn có gói vừa túi.
            </p>
        </div>

        <div class="lp-pricing-grid">
            <div class="lp-pricing-card is-free">
                <div class="lp-pricing-plan">Free</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num">0đ</span>
                </div>
                <div class="lp-pricing-tagline">Dùng thử cơ bản</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    <li><span class="lp-pricing-check">✓</span> Tra kho trên trang chủ</li>
                    <li><span class="lp-pricing-check">✓</span> Giới hạn theo IP (cooldown)</li>
                    <li><span class="lp-pricing-check">✓</span> Không cần đăng nhập Steam</li>
                    <li><span class="lp-pricing-check">✓</span> Giá Empire tương đối USD</li>
                    <li><span class="lp-pricing-check is-muted">—</span> Không lưu kho, không lịch sử</li>
                </ul>
                @include('public.partials.pricing-cta', ['plan' => 'Free', 'free' => true, 'label' => 'Dùng miễn phí'])
            </div>

            <div class="lp-pricing-card">
                <div class="lp-pricing-plan">Pro</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num">19.000đ</span>
                    <span class="lp-pricing-price-unit">/tháng</span>
                </div>
                <div class="lp-pricing-tagline">3 kho theo dõi</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    <li><span class="lp-pricing-check">✓</span> Lưu &amp; theo dõi 3 kho</li>
                    <li><span class="lp-pricing-check">✓</span> Sync tự động mỗi 8h</li>
                    <li><span class="lp-pricing-check">✓</span> Refresh tay ~10 lần/ngày</li>
                    <li><span class="lp-pricing-check">✓</span> Empire coin tích lũy</li>
                    <li><span class="lp-pricing-check">✓</span> Lịch sử giá cơ bản</li>
                </ul>
                @include('public.partials.pricing-cta', ['plan' => 'Pro'])
            </div>

            <div class="lp-pricing-card">
                <div class="lp-pricing-plan">Plus</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num">39.000đ</span>
                    <span class="lp-pricing-price-unit">/tháng</span>
                </div>
                <div class="lp-pricing-tagline">20 kho theo dõi</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    <li><span class="lp-pricing-check">✓</span> Lưu &amp; theo dõi 20 kho</li>
                    <li><span class="lp-pricing-check">✓</span> Sync tự động mỗi 4h</li>
                    <li><span class="lp-pricing-check">✓</span> Refresh tay ~30 lần/ngày</li>
                    <li><span class="lp-pricing-check">✓</span> Empire coin + ưu đãi</li>
                    <li><span class="lp-pricing-check">✓</span> Lịch sử giá chi tiết</li>
                </ul>
                @include('public.partials.pricing-cta', ['plan' => 'Plus'])
            </div>

            <div class="lp-pricing-card is-popular">
                <div class="lp-pricing-ribbon">Phổ biến nhất</div>
                <div class="lp-pricing-plan">Max</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num">69.000đ</span>
                    <span class="lp-pricing-price-unit">/tháng</span>
                </div>
                <div class="lp-pricing-tagline">50 kho theo dõi</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    <li><span class="lp-pricing-check">✓</span> Lưu &amp; theo dõi 50 kho</li>
                    <li><span class="lp-pricing-check">✓</span> Sync tự động mỗi 2h</li>
                    <li><span class="lp-pricing-check">✓</span> Refresh tay ~80 lần/ngày</li>
                    <li><span class="lp-pricing-check">✓</span> Empire coin + ưu đãi cao</li>
                    <li><span class="lp-pricing-check">✓</span> Hỗ trợ ưu tiên</li>
                </ul>
                @include('public.partials.pricing-cta', ['plan' => 'Max', 'primary' => true])
            </div>

            <div class="lp-pricing-card">
                <div class="lp-pricing-plan">Shop</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num">159.000đ</span>
                    <span class="lp-pricing-price-unit">/tháng</span>
                </div>
                <div class="lp-pricing-tagline">Không giới hạn kho*</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    <li><span class="lp-pricing-check">✓</span> Không giới hạn số kho</li>
                    <li><span class="lp-pricing-check">✓</span> Sync tự động mỗi 1h</li>
                    <li><span class="lp-pricing-check">✓</span> Refresh tay không giới hạn*</li>
                    <li><span class="lp-pricing-check">✓</span> Giá Empire &amp; Buff163 chính xác sau mỗi Sync</li>
                    <li><span class="lp-pricing-check">✓</span> Hỗ trợ trực tiếp 1:1</li>
                </ul>
                @include('public.partials.pricing-cta', ['plan' => 'Shop'])
            </div>
        </div>

        <div class="lp-pricing-cycles lp-glass rounded-3 p-4">
            <h3>Ưu đãi khi thanh toán dài hạn</h3>
            <div class="table-responsive">
                <table class="lp-pricing-table">
                    <thead>
                        <tr>
                            <th>Gói</th>
                            <th>1 tháng</th>
                            <th>3 tháng <span class="lp-pricing-save">-5%</span></th>
                            <th>6 tháng <span class="lp-pricing-save">-10%</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Pro</td><td>19.000đ</td><td>54.000đ</td><td>102.000đ</td></tr>
                        <tr><td>Plus</td><td>39.000đ</td><td>111.000đ</td><td>210.000đ</td></tr>
                        <tr><td>Max</td><td>69.000đ</td><td>197.000đ</td><td>372.000đ</td></tr>
                        <tr><td>Shop</td><td>159.000đ</td><td>453.000đ</td><td>858.000đ</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="lp-pricing-note">
            * Gói Shop áp dụng chính sách fair use — chỉ ảnh hưởng khi phát hiện lạm dụng API.
        </p>
    </div>
</section>

@include('landing.footer')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pricing.css') }}?v={{ @filemtime(public_path('css/pricing.css')) ?: 1 }}">
@endpush
