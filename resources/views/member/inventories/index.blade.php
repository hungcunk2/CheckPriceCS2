@extends('layouts.member')

@section('title', 'Kho đồ Steam')
@section('page-title', 'Kho đồ Steam')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: 1 }}">
@endpush

@section('content')
@php
    $slotText = $slotLimit === null
        ? "{$slotUsed} kho (không giới hạn)"
        : "{$slotUsed}/{$slotLimit} kho";
@endphp
<p class="text-muted small mb-3">
    Chỉ hiển thị kho đồ bạn đã thêm vào tài khoản này.
    Gói <strong>{{ $planLabel }}</strong> — {{ $slotText }}.
    <a href="{{ route('public.pricing') }}" class="text-decoration-none">Nâng cấp</a>
</p>

@include('partials.tracked-inventories-table', [
    'inventories' => $inventories,
    'buffConfigured' => $buffConfigured,
    'empireEnabled' => $empireEnabled,
    'tableMode' => 'member',
    'canAddInventory' => $slotLimit === null || $slotUsed < $slotLimit,
])
@endsection

@push('scripts')
@include('partials.tracked-inventories-table-scripts', ['tableMode' => 'member'])
@endpush
