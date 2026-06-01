@php
    use App\Support\SellVenueCompare;
    $venue = $venue ?? null;
@endphp
@if($venue === 'buff')
    <span class="badge text-bg-primary best-sell-badge" title="Giá Buff163 quy đổi cao hơn">Buff</span>
@elseif($venue === 'empire')
    <span class="badge text-bg-warning text-dark best-sell-badge" title="Giá Empire quy đổi cao hơn">Empire</span>
@elseif($venue === 'tie')
    <span class="badge text-bg-secondary best-sell-badge" title="Hai nguồn gần bằng nhau">≈</span>
@else
    <span class="text-muted">—</span>
@endif
