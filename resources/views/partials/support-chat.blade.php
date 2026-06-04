@php
    $chatMessagesUrl = $chatMessagesUrl ?? '';
    $chatPostUrl = $chatPostUrl ?? '';
    $initialMessages = $initialMessages ?? [];
    $chatViewer = $chatViewer ?? 'member';
    $chatTitle = $chatTitle ?? 'Hỗ trợ';
@endphp
<div class="support-chat panel-admin rounded border d-flex flex-column"
     id="support-chat-root"
     data-messages-url="{{ $chatMessagesUrl }}"
     data-post-url="{{ $chatPostUrl }}"
     data-viewer="{{ $chatViewer }}"
     data-initial="{{ json_encode($initialMessages) }}">
    <div class="support-chat-header border-bottom px-3 py-2">
        <strong>{{ $chatTitle }}</strong>
        <span class="small text-muted ms-2" id="support-chat-status"></span>
    </div>
    <div class="support-chat-messages flex-grow-1 px-3 py-3" id="support-chat-messages" aria-live="polite"></div>
    <form class="support-chat-form border-top p-3" id="support-chat-form" enctype="multipart/form-data">
        <div id="support-chat-preview" class="support-chat-preview d-none mb-2">
            <img src="" alt="Xem trước" id="support-chat-preview-img" class="support-chat-preview-img">
            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="support-chat-preview-clear" title="Bỏ ảnh">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="input-group">
            <button type="button" class="btn btn-outline-secondary" id="support-chat-attach" title="Gửi ảnh">
                <i class="fas fa-image"></i>
            </button>
            <input type="file" class="d-none" id="support-chat-image" accept="image/jpeg,image/png,image/webp,image/gif">
            <textarea class="form-control" name="body" id="support-chat-input" rows="2" maxlength="5000"
                      placeholder="Nhập tin nhắn…"></textarea>
            <button type="submit" class="btn btn-primary" id="support-chat-send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="form-text">Có thể gửi ảnh (JPG, PNG, WebP, GIF — tối đa 5MB) kèm hoặc không kèm chữ.</div>
    </form>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/support-chat.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('js/support-chat.js') }}"></script>
@endpush
