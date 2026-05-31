@extends('layouts.admin')

@section('title', $post ? 'Sửa bài blog' : 'Thêm bài blog')
@section('page-title', $post ? 'Sửa bài blog' : 'Thêm bài blog')

@section('content')
<div class="row">
    <div class="col-lg-9">
        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $post ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}">
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
                    <textarea name="content" class="form-control font-monospace @error('content') is-invalid @enderror" rows="18" required>{{ old('content', $post->content ?? '') }}</textarea>
                    @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
