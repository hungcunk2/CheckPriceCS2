@php
    use App\Support\SiteMeta;

    $pageTitle = trim($__env->yieldContent('title'));
    $meta = $meta ?? SiteMeta::forRequest($pageTitle !== '' ? $pageTitle : null);

    $title = $meta['title'] ?? config('site.title');
    $description = $meta['description'] ?? config('site.description');
    $keywords = $meta['keywords'] ?? config('site.keywords');
    $canonical = $meta['canonical'] ?? config('site.url');
    $ogUrl = $meta['url'] ?? $canonical;
    $image = $meta['image'] ?? SiteMeta::absoluteAsset(SiteMeta::ogImagePath());
    $imageWidth = (int) ($meta['image_width'] ?? config('site.og_image_width'));
    $imageHeight = (int) ($meta['image_height'] ?? config('site.og_image_height'));
    $imageAlt = $meta['image_alt'] ?? config('site.og_image_alt');
    $siteName = $meta['site_name'] ?? config('site.name');
    $locale = $meta['locale'] ?? config('site.locale');
    $ogType = $meta['type'] ?? 'website';
    $robots = $meta['robots'] ?? config('site.robots');
    $twitterCard = $meta['twitter_card'] ?? 'summary_large_image';
    $twitterSite = $meta['twitter_site'] ?? config('site.twitter_site');
    $twitterCreator = $meta['twitter_creator'] ?? config('site.twitter_creator');
    $fbAppId = $meta['facebook_app_id'] ?? config('site.facebook_app_id');
    $themeColor = $meta['theme_color'] ?? config('site.theme_color');
    $author = $meta['author'] ?? config('site.author');
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="author" content="{{ $author }}">
<meta name="robots" content="{{ $robots }}">
<meta name="theme-color" content="{{ $themeColor }}">
@if(filled(config('site.google_site_verification')))
<meta name="google-site-verification" content="{{ config('site.google_site_verification') }}">
@endif
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="{{ $ogType }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $locale }}">
<meta property="og:url" content="{{ $ogUrl }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:secure_url" content="{{ $image }}">
<meta property="og:image:alt" content="{{ $imageAlt }}">
@php
    $imageType = match (strtolower(pathinfo(parse_url($image, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
@endphp
<meta property="og:image:type" content="{{ $imageType }}">
@if($imageWidth > 0)
<meta property="og:image:width" content="{{ $imageWidth }}">
@endif
@if($imageHeight > 0)
<meta property="og:image:height" content="{{ $imageHeight }}">
@endif
<link rel="image_src" href="{{ $image }}">

@if(filled($fbAppId))
<meta property="fb:app_id" content="{{ $fbAppId }}">
@endif

<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<meta name="twitter:image:alt" content="{{ $imageAlt }}">
@if(filled($twitterSite))
<meta name="twitter:site" content="{{ $twitterSite }}">
@endif
@if(filled($twitterCreator))
<meta name="twitter:creator" content="{{ $twitterCreator }}">
@endif

<meta itemprop="name" content="{{ $title }}">
<meta itemprop="description" content="{{ $description }}">
<meta itemprop="image" content="{{ $image }}">

<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $siteName,
    'url' => config('site.url'),
    'description' => $description,
    'inLanguage' => 'vi',
    'publisher' => [
        '@type' => 'Organization',
        'name' => $siteName,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SiteMeta::absoluteAsset(SiteMeta::ogImagePath()),
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

@stack('meta')
