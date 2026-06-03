<section class="lp-final-cta">
    <div class="lp-container" style="max-width:64rem">
        <div class="lp-final-cta-box lp-glass-strong">
            <div class="lp-final-cta-glow-1"></div>
            <div class="lp-final-cta-glow-2"></div>
            <div class="lp-final-cta-content">
                <h2 class="lp-section-title">
                    Sẵn sàng kiểm tra giá trị<br>
                    <span class="lp-text-gradient-primary">kho đồ CS2</span> của bạn?
                </h2>
                <p>Miễn phí, không cần đăng nhập. Chỉ mất vài giây để biết kho đồ của bạn đáng giá bao nhiêu.</p>
                @guest
                    <button type="button" class="lp-btn-gradient lp-glow-blue border-0" data-open-auth-modal
                            data-auth-tab="login" data-bs-toggle="modal" data-bs-target="#memberAuthModal">
                        Xem kho ngay
                        <i class="fas fa-arrow-right"></i>
                    </button>
                @else
                    <a href="{{ route('member.inventories.index') }}" class="lp-btn-gradient lp-glow-blue">
                        Xem kho ngay
                        <i class="fas fa-arrow-right"></i>
                    </a>
                @endguest
            </div>
        </div>
    </div>
</section>
