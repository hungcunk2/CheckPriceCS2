@php
    $chatMessagesUrl = $chatMessagesUrl ?? '';
    $chatPostUrl = $chatPostUrl ?? '';
    $initialMessages = $initialMessages ?? [];
    $chatViewer = $chatViewer ?? 'member';
    $chatTitle = $chatTitle ?? 'Hỗ trợ';
    $supportChatCssVer = @filemtime(public_path('css/support-chat.css')) ?: 1;
    $supportChatJsVer = @filemtime(public_path('js/support-chat.js')) ?: 1;
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
            <button type="button" class="btn btn-sm btn-outline-danger" id="support-chat-preview-clear" title="Bỏ ảnh">
                <i class="fas fa-times"></i> Bỏ ảnh
            </button>
        </div>
        <div class="support-chat-compose input-group">
            <input type="file"
                   class="support-chat-file-input"
                   id="support-chat-image"
                   name="image"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   tabindex="-1"
                   aria-hidden="true">
            <label for="support-chat-image" class="btn btn-outline-secondary support-chat-attach-btn mb-0" id="support-chat-attach-label">
                <i class="fas fa-image" aria-hidden="true"></i>
                <span class="ms-1">Gửi ảnh</span>
            </label>
            <textarea class="form-control" name="body" id="support-chat-input" rows="2" maxlength="5000"
                      placeholder="Nhập tin nhắn…"></textarea>
            <button type="submit" class="btn btn-primary" id="support-chat-send">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                <span class="ms-1">Gửi</span>
            </button>
        </div>
        <div class="form-text" id="support-chat-hint">Bấm <strong>Gửi ảnh</strong> để chọn hình (JPG, PNG, WebP, GIF — tối đa 5MB).</div>
    </form>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/support-chat.css') }}?v={{ $supportChatCssVer }}">
@endpush
@push('scripts')
<script src="{{ asset('js/support-chat.js') }}?v={{ $supportChatJsVer }}" defer></script>
@endpush
