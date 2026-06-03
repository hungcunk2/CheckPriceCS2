@extends('layouts.admin')

@section('title', 'Quản lý kho đồ')
@section('page-title', 'Quản lý kho Steam')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
@include('partials.tracked-inventories-table', [
    'inventories' => $inventories,
    'buffConfigured' => $buffConfigured,
    'empireEnabled' => $empireEnabled,
    'tableMode' => 'admin',
])
@endsection

@push('scripts')
@include('partials.tracked-inventories-table-scripts', ['tableMode' => 'admin'])
@endpush
