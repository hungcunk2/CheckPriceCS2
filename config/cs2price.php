<?php

return [

    /*
    | Kho đồ: mặc định cs.trade trước, lỗi thì Steam. INVENTORY_SOURCE=steam = chỉ Steam.
    */
    'inventory_source' => env('INVENTORY_SOURCE', 'cstrade'),
    'inventory_fallback_steam' => filter_var(env('INVENTORY_FALLBACK_STEAM', true), FILTER_VALIDATE_BOOL),
    'cstrade_probe_steam_id' => env('CSTRADE_PROBE_STEAM_ID', '76561198959660892'),

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
    // Item đã có giá: chỉ gọi Buff lại sau N giây (mặc định 4 giờ, khớp chu kỳ sync).
    'price_refresh_seconds' => (int) env('BUFF_PRICE_REFRESH_SECONDS', env('BUFF_PRICE_CACHE_SECONDS', 14400)),
    'price_cache_seconds' => (int) env('BUFF_PRICE_CACHE_SECONDS', 14400),

    // Tự động quét kho Steam + giá Buff (scheduler).
    'price_auto_sync_enabled' => filter_var(env('BUFF_PRICE_AUTO_SYNC', true), FILTER_VALIDATE_BOOL),
    'price_auto_sync_minutes' => (int) env('BUFF_PRICE_AUTO_SYNC_MINUTES', 240),
    'request_delay_ms' => (int) env('BUFF_REQUEST_DELAY_MS', 800),
    'buff_concurrency' => (int) env('BUFF_CONCURRENCY', 1),
    'check_max_execution_seconds' => (int) env('CHECK_MAX_EXECUTION_SECONDS', 600),
    'steam_inventory_page_size' => (int) env('STEAM_INVENTORY_PAGE_SIZE', 2000),
    'steam_request_delay_ms' => (int) env('STEAM_REQUEST_DELAY_MS', 1500),
    // Nghỉ giữa mỗi kho khi phải gọi Steam (ms). Mặc định 20 phút — tránh 429.
    'steam_request_delay_between_inventories_ms' => (int) env('STEAM_REQUEST_DELAY_BETWEEN_INVENTORIES_MS', 1_200_000),
    // Cache list skin Steam: cron dùng lại, không gọi API (kho ít đổi trong ngày).
    'steam_inventory_cache_seconds' => (int) env('STEAM_INVENTORY_CACHE_SECONDS', 86400),

    // Khách tra giá trên trang chủ: tối đa 1 lần / IP / N giây (mặc định 5 phút).
    'guest_check_cooldown_seconds' => (int) env('GUEST_CHECK_COOLDOWN_SECONDS', 300),

    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'price_current_window_hours' => (int) env('BUFF_PRICE_CURRENT_WINDOW_HOURS', 2),
    'price_history_days' => (int) env('PRICE_HISTORY_DAYS', 90),
    'price_history_max_points' => (int) env('PRICE_HISTORY_MAX_POINTS', 3000),
];
