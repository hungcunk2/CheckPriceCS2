<?php

return [
    'steam_api_key' => env('STEAM_API_KEY'),
    'steam_profile_cache_seconds' => (int) env('STEAM_PROFILE_CACHE_SECONDS', 3600),
    'buff_session' => env('BUFF163_SESSION'),
    'buff_csrf_token' => env('BUFF163_CSRF_TOKEN'),
    'cny_to_vnd' => (float) env('CNY_TO_VND', 3750),
    // 1 USD = N VND (bắc cầu: CNY → VND → USD).
    'vnd_to_usd' => (float) env('VND_TO_USD', 26700),
    // Item đã có giá: chỉ gọi Buff lại sau N giây (mặc định 2 giờ).
    'price_refresh_seconds' => (int) env('BUFF_PRICE_REFRESH_SECONDS', env('BUFF_PRICE_CACHE_SECONDS', 7200)),
    'price_cache_seconds' => (int) env('BUFF_PRICE_CACHE_SECONDS', 7200),

    // Tự động đồng bộ giá (scheduler).
    'price_auto_sync_enabled' => filter_var(env('BUFF_PRICE_AUTO_SYNC', true), FILTER_VALIDATE_BOOL),
    'price_auto_sync_minutes' => (int) env('BUFF_PRICE_AUTO_SYNC_MINUTES', 10),
    'request_delay_ms' => (int) env('BUFF_REQUEST_DELAY_MS', 350),
    'buff_concurrency' => (int) env('BUFF_CONCURRENCY', 2),
    'check_max_execution_seconds' => (int) env('CHECK_MAX_EXECUTION_SECONDS', 600),
    'steam_inventory_page_size' => (int) env('STEAM_INVENTORY_PAGE_SIZE', 2000),

    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'price_current_window_hours' => (int) env('BUFF_PRICE_CURRENT_WINDOW_HOURS', 2),
    'price_history_days' => (int) env('PRICE_HISTORY_DAYS', 90),
    'price_history_max_points' => (int) env('PRICE_HISTORY_MAX_POINTS', 3000),
];
