@extends('layouts.landing')

@section('content')
@include('landing.nav')
@include('partials.flash-alerts')
@include('landing.hero')
@include('landing.stats')
@include('landing.features')
@include('landing.how-it-works')
@include('landing.benefits')
@include('landing.testimonials')
@include('landing.faq')
@include('landing.final-cta')
@include('landing.footer')
@endsection

@push('scripts')
@if(request()->boolean('registered'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.alert-success')) return;
    var wrap = document.createElement('div');
    wrap.className = 'lp-container';
    wrap.style.cssText = 'max-width:72rem;margin:0 auto;padding:0 1rem';
    wrap.innerHTML = '<div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">Đăng ký thành công! Bạn đã được đăng nhập.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    var nav = document.querySelector('.lp-nav-wrap');
    if (nav && nav.parentNode) nav.parentNode.insertBefore(wrap, nav.nextSibling);
});
</script>
@endif
@endpush
