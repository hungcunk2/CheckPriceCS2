@php
    use App\Support\SteamImageUrl;

    $src = $src ?? '';
    $largeSrc = SteamImageUrl::large($src) ?? $src;
    $caption = $caption ?? '';
    $imgClass = trim(($class ?? '').' img-zoomable');
    $imgStyle = $style ?? '';
@endphp
@if($src !== '')
    <button
        type="button"
        class="img-zoom-trigger {{ $triggerClass ?? '' }}"
        data-zoom-src="{{ $largeSrc }}"
        @if($caption !== '') data-zoom-caption="{{ $caption }}" @endif
        aria-label="Phóng to ảnh"
    >
        <img src="{{ $src }}" alt="{{ $caption }}" class="{{ $imgClass }}" @if($imgStyle !== '') style="{{ $imgStyle }}" @endif loading="lazy">
    </button>
@endif
