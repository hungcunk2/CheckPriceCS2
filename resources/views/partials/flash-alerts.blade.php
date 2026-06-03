@if(session('register_magic_success'))
    <div class="lp-container" style="max-width:72rem;margin:0 auto;padding:0 1rem">
        <div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">
            {{ session('register_magic_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    </div>
@endif
@if(session('register_success'))
    <div class="lp-container" style="max-width:72rem;margin:0 auto;padding:0 1rem">
        <div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">
            {{ session('register_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    </div>
@endif
@if(session('error'))
    <div class="lp-container" style="max-width:72rem;margin:0 auto;padding:0 1rem">
        <div class="alert alert-danger alert-dismissible fade show mt-3 mb-0" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    </div>
@endif
