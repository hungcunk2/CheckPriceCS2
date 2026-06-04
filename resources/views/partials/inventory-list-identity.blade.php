@php
    $invAvatar = \App\Support\InventoryDisplay::avatarUrl($inventory);
@endphp
<div class="d-flex align-items-center gap-2">
    @if($invAvatar)
        <img src="{{ $invAvatar }}" alt="" class="steam-avatar image-zoomable flex-shrink-0" width="48" height="48" loading="lazy" style="--steam-avatar-size: 48px">
    @else
        <div class="steam-avatar steam-avatar--placeholder flex-shrink-0" style="--steam-avatar-size: 48px">
            <i class="fab fa-steam"></i>
        </div>
    @endif
    <div class="min-w-0">
        <div class="fw-semibold">{{ \App\Support\InventoryDisplay::title($inventory) }}</div>
        <div class="small text-muted text-truncate" style="max-width:240px">{{ $inventory->url ?? '' }}</div>
    </div>
</div>
