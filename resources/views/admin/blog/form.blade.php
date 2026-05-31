@extends('layouts.admin')

@section('title', $post ? 'Sửa bài blog' : 'Thêm bài blog')
@section('page-title', $post ? 'Sửa bài blog' : 'Thêm bài blog')

@section('content')
<div class="row">
    <div class="col-lg-9">
        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $post ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}" enctype="multipart/form-data">
                @csrf
                @if($post)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                        value="{{ old('title', $post->title ?? '') }}" required maxlength="200">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Mô tả ngắn <span class="text-danger">*</span></label>
                    <textarea name="excerpt" class="form-control @error('excerpt') is-invalid @enderror" rows="2" required maxlength="500">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                    @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Hiển thị trên thẻ bài viết ở trang Blog.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-control @error('meta_description') is-invalid @enderror" rows="2" maxlength="320"
                        placeholder="Hướng dẫn định giá kho đồ CS2 chuẩn Buff163 năm 2026. Kiểm tra giá inventory CS2 nhanh chóng, chính xác theo CNY và VND chỉ trong vài giây.">{{ old('meta_description', $post->meta_description ?? '') }}</textarea>
                    @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Mô tả SEO cho Google/Facebook (tối đa 320 ký tự). Để trống sẽ dùng Mô tả ngắn.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ảnh nền bài viết</label>
                    @if(!empty($post?->cover_url))
                        <div class="mb-2">
                            <img src="{{ $post->cover_url }}" alt="Ảnh nền hiện tại" class="blog-cover-preview rounded border">
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="remove_cover_image" value="1" class="form-check-input" id="remove_cover_image">
                            <label class="form-check-label" for="remove_cover_image">Xóa ảnh nền hiện tại</label>
                        </div>
                    @endif
                    <input type="file" name="cover_image" id="cover_image" accept="image/jpeg,image/png,image/webp"
                        class="form-control @error('cover_image') is-invalid @enderror">
                    @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">JPG, PNG hoặc WebP, tối đa 4MB. Tự cắt giữa và resize 1200×675 (16:9) khi lưu.</div>
                    <img id="cover-image-preview" src="#" alt="" class="blog-cover-preview rounded border mt-2" style="display:none">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Ngày đăng <span class="text-danger">*</span></label>
                        <input type="date" name="published_at" class="form-control @error('published_at') is-invalid @enderror"
                            value="{{ old('published_at', $post->published_at ?? now()->format('Y-m-d')) }}" required>
                        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Thời gian đọc</label>
                        <input type="text" name="read_time" class="form-control @error('read_time') is-invalid @enderror"
                            value="{{ old('read_time', $post->read_time ?? '5 phút') }}" maxlength="32">
                        @error('read_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control @error('tags') is-invalid @enderror"
                            value="{{ old('tags', isset($post) ? implode(', ', $post->tags ?? []) : '') }}"
                            placeholder="Hướng dẫn, Buff163">
                        @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                    @php
                        use App\Support\BlogContent;
                        $editorContent = old('content') !== null
                            ? BlogContent::forEditor(old('content'))
                            : BlogContent::forEditor($post->content ?? '');
                    @endphp
                    <textarea id="blog-content" name="content" class="form-control @error('content') is-invalid @enderror" rows="18" required>{!! $editorContent !!}</textarea>
                    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Chèn link ẩn: bôi đen chữ (vd. CheckPriceCS2) → icon liên kết → dán URL → chọn <strong>Link ẩn</strong>.</div>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published"
                        @checked(old('is_published', $post->is_published ?? true))>
                    <label class="form-check-label" for="is_published">Hiển thị công khai</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu bài viết</button>
                    <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tox-tinymce { border-radius: 0.375rem !important; }
    .blog-cover-preview {
        display: block;
        max-width: 100%;
        max-height: 12rem;
        object-fit: cover;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const uploadUrl = @json(route('admin.blog.upload-image'));

    function uploadImageFile(file, progress) {
        return new Promise(function (resolve, reject) {
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = function (event) {
                if (event.lengthComputable && typeof progress === 'function') {
                    progress(event.loaded / event.total * 100);
                }
            };

            xhr.onload = function () {
                if (xhr.status === 422) {
                    reject('File không hợp lệ (JPG/PNG/WebP, tối đa 4MB)');
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    reject('Upload thất bại');
                    return;
                }

                try {
                    const json = JSON.parse(xhr.responseText);
                    if (json.location) {
                        resolve(json.location);
                    } else {
                        reject(json.message || 'Upload thất bại');
                    }
                } catch (error) {
                    reject('Upload thất bại');
                }
            };

            xhr.onerror = function () {
                reject('Upload thất bại');
            };

            xhr.send(formData);
        });
    }

    tinymce.init({
        selector: '#blog-content',
        height: 520,
        menubar: 'edit view insert format table',
        language: 'vi',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.12.9/langs6/vi.js',
        base_url: 'https://cdn.jsdelivr.net/npm/tinymce@7.6.0',
        suffix: '.min',
        plugins: 'lists link image table code wordcount autolink',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | removeformat code',
        font_family_formats: 'Roboto Regular=Roboto Regular,sans-serif;Roboto Bold=Roboto Bold,sans-serif;Mặc định=system-ui,-apple-system,sans-serif; Arial=Arial,Helvetica,sans-serif; Georgia=Georgia,serif; Times New Roman=Times New Roman,Times,serif; Tahoma=Tahoma,Arial,sans-serif; Courier New=Courier New,Courier,monospace',
        font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px 36px',
        link_default_protocol: 'https',
        link_class_list: [
            { title: 'Link thường', value: '' },
            { title: 'Link ẩn', value: 'lp-link-hidden' },
        ],
        default_link_target: '_blank',
        branding: false,
        promotion: false,
        automatic_uploads: true,
        paste_data_images: true,
        file_picker_types: 'image',
        images_upload_handler: function (blobInfo, progress) {
            return uploadImageFile(blobInfo.blob(), progress);
        },
        file_picker_callback: function (callback, value, meta) {
            if (meta.filetype !== 'image') {
                return;
            }

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/jpeg,image/png,image/webp,image/gif';

            input.onchange = function () {
                const file = input.files && input.files[0];
                if (!file) {
                    return;
                }

                uploadImageFile(file)
                    .then(function (url) {
                        callback(url, { alt: file.name.replace(/\.[^.]+$/, '') });
                    })
                    .catch(function () {
                        alert('Không upload được ảnh. Thử lại hoặc chọn file nhỏ hơn 4MB.');
                    });
            };

            input.click();
        },
        content_style: [
            '@import url(\'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap\');',
            'body { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; line-height: 1.6; }',
            '[style*="Roboto Regular"] { font-family: Roboto, sans-serif !important; font-weight: 400 !important; }',
            '[style*="Roboto Bold"] { font-family: Roboto, sans-serif !important; font-weight: 700 !important; }',
            'a.lp-link-hidden { color: inherit; text-decoration: none; cursor: pointer; }',
        ].join('\n'),
        setup: function (editor) {
            editor.on('change input', function () {
                editor.save();
            });
        },
    });

    form.addEventListener('submit', function () {
        if (tinymce.get('blog-content')) {
            tinymce.triggerSave();
        }
    });

    const coverInput = document.getElementById('cover_image');
    const coverPreview = document.getElementById('cover-image-preview');
    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', function () {
            const file = coverInput.files && coverInput.files[0];
            if (!file) {
                coverPreview.style.display = 'none';
                return;
            }
            coverPreview.src = URL.createObjectURL(file);
            coverPreview.style.display = 'block';
        });
    }
});
</script>
@endpush
