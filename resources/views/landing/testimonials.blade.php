<section class="lp-testimonials">
    <div class="lp-container">
        <div class="lp-testimonials-header">
            <div class="lp-section-label">Đánh giá</div>
            <h2 class="lp-section-title">
                Cộng đồng <span class="lp-text-gradient-accent">CS2 Việt Nam</span> nói gì
            </h2>
        </div>
        <div class="lp-testimonials-grid">
            @php
                $reviews = [
                    ['initials' => 'MT', 'name' => 'Minh Trần', 'role' => 'CS2 Trader', 'quote' => 'Quá tiện luôn, mình check inventory của mấy account trade chỉ trong vài giây. Giá Buff163 rất sát thực tế.'],
                    ['initials' => 'HP', 'name' => 'Hoàng Phạm', 'role' => 'Global Elite', 'quote' => 'Trước phải mở từng item trên Buff163 để xem giá, giờ chỉ cần dán link Steam là xong. Khuyên dùng cho ae trader.'],
                    ['initials' => 'LN', 'name' => 'Lan Nguyễn', 'role' => 'Skin Collector', 'quote' => 'Giao diện sạch, không quảng cáo, không phải đăng nhập gì cả. Đúng kiểu công cụ mình cần cho việc quản lý kho.'],
                ];
            @endphp
            @foreach($reviews as $review)
                <div class="lp-testimonial lp-glass">
                    <div class="lp-stars">
                        @for($i = 0; $i < 5; $i++)
                            <i class="fas fa-star"></i>
                        @endfor
                    </div>
                    <p class="lp-testimonial-quote">"{{ $review['quote'] }}"</p>
                    <div class="lp-testimonial-author">
                        <div class="lp-testimonial-avatar">{{ $review['initials'] }}</div>
                        <div>
                            <div class="lp-testimonial-name">{{ $review['name'] }}</div>
                            <div class="lp-testimonial-role">{{ $review['role'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
