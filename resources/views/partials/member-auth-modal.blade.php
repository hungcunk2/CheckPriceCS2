@php
    $authMode = request('auth', session('auth_tab', 'login'));
@endphp
<div class="modal fade" id="memberAuthModal" tabindex="-1" aria-labelledby="memberAuthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ma-modal-dialog">
        <div class="modal-content ma-modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-0 pb-4 px-3 px-sm-4">
                @include('partials.member-auth-forms', [
                    'formIdSuffix' => 'modal',
                    'mode' => $authMode,
                    'authRedirectTo' => route('public.landing'),
                ])
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    @include('partials.member-auth-scripts', ['autoOpenModal' => true])
    @endpush
@endonce
