@php
    use App\Support\InventoryDisplay;
    $title = InventoryDisplay::title($inventory);
    $avatar = InventoryDisplay::avatarUrl($inventory);
    $size = (int) ($size ?? 56);
    $headingTag = in_array($heading ?? 'h5', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? ($heading ?? 'h5') : 'h5';
    $steamUrl = $inventory->url ?? null;
    if (! $steamUrl && ! empty($inventory->steam_id)) {
        $steamUrl = 'https://steamcommunity.com/profiles/'.$inventory->steam_id;
    }
@endphp
<div class="inventory-identity d-flex align-items-center gap-3 {{ $class ?? '' }}" style="--steam-avatar-size: {{ $size }}px">
    @if($avatar)
        <img
            src="{{ $avatar }}"
            alt=""
            class="steam-avatar flex-shrink-0"
            width="{{ $size }}"
            height="{{ $size }}"
            loading="lazy"
        >
    @else
        <div class="steam-avatar steam-avatar--placeholder flex-shrink-0">
            <i class="fab fa-steam"></i>
        </div>
    @endif
    <div class="min-w-0">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($headingTag === 'h1')
                <h1 class="mb-0 {{ $headingClass ?? '' }}">{{ $title }}</h1>
            @elseif($headingTag === 'h4')
                <h4 class="mb-0 {{ $headingClass ?? '' }}">{{ $title }}</h4>
            @else
                <h5 class="mb-0 {{ $headingClass ?? '' }}">{{ $title }}</h5>
            @endif
            @if($steamUrl)
                <a
                    href="{{ $steamUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="steam-profile-link"
                    title="Mở trên Steam"
                    aria-label="Mở kho Steam"
                    onclick="event.stopPropagation()"
                >
                    <i class="fab fa-steam" aria-hidden="true"></i>
                </a>
            @endif
        </div>
        @if(!empty($subtitle))
            <div class="small text-muted mt-1">{{ $subtitle }}</div>
        @endif
    </div>
</div>
