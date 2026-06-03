@extends('layouts.auth-member')

@section('content')
    @include('partials.member-auth-forms', [
        'mode' => $mode ?? 'login',
        'otpSent' => $otpSent ?? false,
        'authRedirectTo' => route('login', ['mode' => $mode ?? 'login']),
    ])
@endsection

@push('scripts')
    @include('partials.member-auth-scripts', ['autoOpenModal' => false])
@endpush
