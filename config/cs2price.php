<?php

return [

    /*
    | Kho đồ: mặc định Steam. Có thể bật CS2Cap inventory nếu cần phase Doppler/Gamma.
    | INVENTORY_SOURCE=steam | cs2cap
    */
    'inventory_source' => env('INVENTORY_SOURCE', 'cs2cap'),

    'steam_api_key' => env('STEAM_API_KEY'),
    'steam_profile_cache_seconds' => (int) env('STEAM_PROFILE_CACHE_SECONDS', 3600),
    'buff_session' => env('BUFF163_SESSION'),
    'buff_csrf_token' => env('BUFF163_CSRF_TOKEN'),
    'buff_account_label' => env('BUFF163_ACCOUNT_LABEL', 'acc-1'),

    /*
    | Acc Buff dự phòng — khi acc 1 bị 429/403, tự chuyển sang acc tiếp theo.
    | Thêm BUFF163_SESSION_2, BUFF163_CSRF_TOKEN_2, ... (tối đa 9 acc phụ).
    */
    'buff_extra_accounts' => array_values(array_filter(array_map(
        static function (int $index): ?array {
            $session = env('BUFF163_SESSION_'.$index);
            if (! filled($session)) {
                return null;
            }

            return [
                'label' => env('BUFF163_ACCOUNT_LABEL_'.$index, 'acc-'.$index),
                'session' => $session,
                'csrf' => env('BUFF163_CSRF_TOKEN_'.$index),
            ];
        },
        range(2, 10)
    ))),
    'cny_to_vnd' => (float) env('CNY_TO_VND', 3750),
    // 1 USD = N VND (bắc cầu: CNY → VND → USD).
    'vnd_to_usd' => (float) env('VND_TO_USD', 26700),
    // Skin đơn giá Buff (CNY/tệ) dưới ngưỡng: ẩn danh sách, không gọi API giá (đã có cache).
    'min_item_cny_value' => (float) env('MIN_ITEM_CNY_VALUE', 1),
    // Item đã có giá: chỉ gọi Buff lại sau N giây (mặc định 4 giờ, khớp chu kỳ sync).
    'price_refresh_seconds' => (int) env('BUFF_PRICE_REFRESH_SECONDS', env('BUFF_PRICE_CACHE_SECONDS', 14400)),
    'price_cache_seconds' => (int) env('BUFF_PRICE_CACHE_SECONDS', 14400),
    // Cache giá theo item trong DB (chia sẻ giữa mọi lượt quét), mặc định 4 giờ.
    'db_item_price_cache_seconds' => (int) env('DB_ITEM_PRICE_CACHE_SECONDS', 14400),

    // Tự động quét kho Steam + giá Buff (scheduler).
    'price_auto_sync_enabled' => filter_var(env('BUFF_PRICE_AUTO_SYNC', true), FILTER_VALIDATE_BOOL),
    'price_auto_sync_minutes' => (int) env('BUFF_PRICE_AUTO_SYNC_MINUTES', 15),
    // Đồng bộ kho/giá qua queue (cron + nút ⟳). Tắt hoặc QUEUE_CONNECTION=sync = chạy đồng bộ trong process.
    'sync_use_queue' => filter_var(env('SYNC_USE_QUEUE', true), FILTER_VALIDATE_BOOL),
    'request_delay_ms' => (int) env('BUFF_REQUEST_DELAY_MS', 800),
    'buff_concurrency' => (int) env('BUFF_CONCURRENCY', 1),
    'check_max_execution_seconds' => (int) env('CHECK_MAX_EXECUTION_SECONDS', 600),
    'steam_inventory_page_size' => (int) env('STEAM_INVENTORY_PAGE_SIZE', 2000),
    'steam_request_delay_ms' => (int) env('STEAM_REQUEST_DELAY_MS', 1500),
    // Nghỉ giữa mỗi kho khi phải gọi Steam (ms). Mặc định 20 phút — tránh 429.
    'steam_request_delay_between_inventories_ms' => (int) env('STEAM_REQUEST_DELAY_BETWEEN_INVENTORIES_MS', 1_200_000),
    // Cache danh sách skin (Steam/CS2Cap): auto-sync dùng lại tối đa N giây (mặc định 4h, khớp cache giá).
    'steam_inventory_cache_seconds' => (int) env('STEAM_INVENTORY_CACHE_SECONDS', 14400),

    // Khách tra giá trên trang chủ: tối đa 1 lần / IP / N giây (mặc định 5 phút).
    'guest_check_cooldown_seconds' => (int) env('GUEST_CHECK_COOLDOWN_SECONDS', 300),
    // Số skin mỗi lần gọi Buff/Empire khi tra giá từng bước trên trang chủ.
    'guest_check_batch_size' => (int) env('GUEST_CHECK_BATCH_SIZE', 12),
    'guest_check_cache_minutes' => (int) env('GUEST_CHECK_CACHE_MINUTES', 15),

    'registration_otp_ttl_minutes' => (int) env('REGISTRATION_OTP_TTL_MINUTES', 10),
    'registration_otp_resend_cooldown_seconds' => (int) env('REGISTRATION_OTP_RESEND_COOLDOWN', 60),

    'timezone' => env('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh',
    'price_current_window_hours' => (int) env('BUFF_PRICE_CURRENT_WINDOW_HOURS', 2),
    'price_history_days' => (int) env('PRICE_HISTORY_DAYS', 90),
    'price_history_max_points' => (int) env('PRICE_HISTORY_MAX_POINTS', 3000),

    // Snapshot tổng giá trị kho + từng skin (báo cáo admin), giữ tối đa N ngày.
    'inventory_snapshot_days' => (int) env('INVENTORY_SNAPSHOT_DAYS', 90),

    /*
    | CSGOEmpire — giá tham chiếu từ withdraw market (listing thấp nhất).
    | empire_fetch_mode=paginate: quét theo trang (nhanh, ~20 req/10s).
    | empire_fetch_mode=search: từng skin (chậm, ~3 req/10s khi có search).
    | empire_fetch_mode=auto: paginate trước, còn thiếu mới search.
    */
    'empire_fetch_mode' => env('EMPIRE_FETCH_MODE', 'auto'),
    'empire_bulk_cache_seconds' => (int) env('EMPIRE_BULK_CACHE_SECONDS', 600),
    'empire_bulk_max_pages' => (int) env('EMPIRE_BULK_MAX_PAGES', 25),
    'empire_page_delay_ms' => (int) env('EMPIRE_PAGE_DELAY_MS', 550),
    'empire_bulk_per_page' => (int) env('EMPIRE_BULK_PER_PAGE', 0),
    // Proxy 5Stars: sau N giây gọi lại get.php để lấy IP mới (0 = chỉ theo message "die sau Xs").
    'fivestars_proxy_rotate_seconds' => (int) env('FIVESTARS_PROXY_ROTATE_SECONDS', 62),

    'empire_enabled' => filter_var(env('EMPIRE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'empire_api_key' => env('CSGOEMPIRE_API_KEY'),
    'empire_account_label' => env('CSGOEMPIRE_ACCOUNT_LABEL', 'empire-1'),
    'empire_extra_api_keys' => array_values(array_filter(array_map(
        static function (int $index): ?array {
            $key = env('CSGOEMPIRE_API_KEY_'.$index);
            if (! filled($key)) {
                return null;
            }

            return [
                'label' => env('CSGOEMPIRE_ACCOUNT_LABEL_'.$index, 'empire-'.$index),
                'api_key' => $key,
            ];
        },
        range(2, 6)
    ))),
    'empire_bulk_parallel' => filter_var(env('EMPIRE_BULK_PARALLEL', true), FILTER_VALIDATE_BOOL),
    // 1 coin Empire ≈ bao nhiêu USD (thường ~0.614 khi nạp; chỉnh theo thực tế).
    'empire_coin_to_usd' => (float) env('EMPIRE_COIN_TO_USD', 0.6143),
    // ₫/coin = empire_coin_to_usd × vnd_to_usd (admin nhập coin→USD).
    'empire_coin_to_vnd' => (float) env('EMPIRE_COIN_TO_USD', 0.6143) * (float) env('VND_TO_USD', 26700),
    'empire_price_refresh_seconds' => (int) env('EMPIRE_PRICE_REFRESH_SECONDS', env('BUFF_PRICE_CACHE_SECONDS', 14400)),
    'empire_not_found_cache_seconds' => (int) env('EMPIRE_NOT_FOUND_CACHE_SECONDS', 3600),
    // Doppler/Gamma: cache "không có listing" ngắn để tự hồi nhanh khi có phase.
    'empire_phase_not_found_cache_seconds' => (int) env('EMPIRE_PHASE_NOT_FOUND_CACHE_SECONDS', 300),
    'empire_error_cache_seconds' => (int) env('EMPIRE_ERROR_CACHE_SECONDS', 300),
    // Sau khi hết key Empire khả dụng, chờ N giây rồi quét lại các skin lỗi đó (0 = tắt).
    'empire_pool_exhausted_retry_seconds' => (int) env('EMPIRE_POOL_EXHAUSTED_RETRY_SECONDS', 70),
    'empire_search_delay_ms' => (int) env('EMPIRE_SEARCH_DELAY_MS', 3500),
    // Tra nhanh trang chủ (giới hạn để không timeout).
    'empire_max_fetches_per_check' => (int) env('EMPIRE_MAX_FETCHES_PER_CHECK', 15),
    // Đồng bộ admin/cron: 0 = không giới hạn (78 skin ≈ vài phút).
    'empire_max_fetches_per_sync' => (int) env('EMPIRE_MAX_FETCHES_PER_SYNC', 0),
    // Nút ⟳ admin: quét tối đa N trang Empire mỗi key (× số key đang bật).
    'empire_http_max_pages' => (int) env('EMPIRE_HTTP_MAX_PAGES', 12),
    // 0 = auto: số key × empire_http_max_searches_per_key
    'empire_http_max_searches' => (int) env('EMPIRE_HTTP_MAX_SEARCHES', 0),
    'empire_http_max_searches_per_key' => (int) env('EMPIRE_HTTP_MAX_SEARCHES_PER_KEY', 10),
    // Nút ⟳ admin: 0 = search mọi skin còn thiếu trong kho
    'empire_admin_max_searches' => (int) env('EMPIRE_ADMIN_MAX_SEARCHES', 0),
    'empire_admin_max_pages' => (int) env('EMPIRE_ADMIN_MAX_PAGES', 15),

    /*
    | CS2Cap (tùy chọn) — aggregator: Buff CNY + Empire USD (hai currency / hai request mỗi skin).
    */
    'cs2cap_enabled' => filter_var(env('CS2CAP_ENABLED', false), FILTER_VALIDATE_BOOL),
    'cs2cap_api_key' => env('CS2CAP_API_KEY'),
    'cs2cap_key_label' => env('CS2CAP_KEY_LABEL', 'cs2cap-1'),
    'cs2cap_extra_api_keys' => array_values(array_filter(array_map(
        static function (int $index): ?array {
            $key = env('CS2CAP_API_KEY_'.$index);
            if (! filled($key)) {
                return null;
            }

            return [
                'label' => env('CS2CAP_KEY_LABEL_'.$index, 'cs2cap-'.$index),
                'api_key' => $key,
            ];
        },
        range(2, 20)
    ))),
    'cs2cap_base_url' => env('CS2CAP_API_BASE', 'https://api.cs2c.app/v1'),
    'cs2cap_buff_currency' => env('CS2CAP_BUFF_CURRENCY', 'CNY'),
    'cs2cap_empire_currency' => env('CS2CAP_EMPIRE_CURRENCY', 'USD'),
    'cs2cap_cooldown_seconds' => (int) env('CS2CAP_COOLDOWN_SECONDS', 30),
    'cs2cap_use_inventory' => filter_var(env('CS2CAP_USE_INVENTORY', false), FILTER_VALIDATE_BOOL),
    'cs2cap_use_buff' => filter_var(env('CS2CAP_USE_BUFF', false), FILTER_VALIDATE_BOOL),

    // Cache ảnh catalog CS2Cap theo market_hash_name (giây), mặc định 30 ngày.
    'cs2cap_catalog_image_cache_seconds' => (int) env('CS2CAP_CATALOG_IMAGE_CACHE_SECONDS', 86400 * 30),

    // Khi bật proxy 5Stars: tải ảnh skin qua proxy (/api/guest/item-image/stream). Avatar profile: URL Steam trực tiếp.
    'steam_item_image_via_proxy' => filter_var(env('STEAM_ITEM_IMAGE_VIA_PROXY', true), FILTER_VALIDATE_BOOL),

    /*
    | Thanh toán: cấu hình trong Admin → TK thanh toán (payment_settings).
    */
    'payment' => [],
];
