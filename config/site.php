<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site metadata (SEO, Open Graph, X, Zalo, Google)
    |--------------------------------------------------------------------------
    |
    | Ảnh chia sẻ (og:image): nên 1200×630 px, đặt tại public/images/og-share.jpg
    | và set SITE_OG_IMAGE=/images/og-share.jpg trong .env
    |
    */

    'name' => env('SITE_NAME', 'CheckPrice CS2'),

    'title' => env('SITE_TITLE', 'CheckPrice CS2 — Tra giá kho CS2 theo Buff163'),

    'description' => env(
        'SITE_DESCRIPTION',
        'Xem giá trị kho đồ CS2 theo Buff163, quy đổi VND/USD, skin đang trade hold và lịch sử giá. Cập nhật từ kho Steam public.'
    ),

    'keywords' => env(
        'SITE_KEYWORDS',
        'CS2, giá skin CS2, Buff163, inventory CS2, giá kho CS2, trade hold, Steam inventory, check giá CS2'
    ),

    'url' => rtrim(env('APP_URL', 'https://checkpricecs2.io.vn'), '/'),

    'locale' => env('SITE_LOCALE', 'vi_VN'),

    'og_image' => env('SITE_OG_IMAGE', '/images/og-share.jpg'),

    'og_image_width' => (int) env('SITE_OG_IMAGE_WIDTH', 1200),

    'og_image_height' => (int) env('SITE_OG_IMAGE_HEIGHT', 630),

    'og_image_alt' => env('SITE_OG_IMAGE_ALT', 'CheckPrice CS2 — Tra giá kho CS2'),

    'twitter_site' => env('SITE_TWITTER', ''),

    'twitter_creator' => env('SITE_TWITTER_CREATOR', ''),

    'facebook_app_id' => env('FACEBOOK_APP_ID', ''),

    'robots' => env('SITE_ROBOTS', 'index, follow'),

    'theme_color' => env('SITE_THEME_COLOR', '#0d1117'),

    'author' => env('SITE_AUTHOR', 'Nguyễn Tuấn Hùng'),

];
