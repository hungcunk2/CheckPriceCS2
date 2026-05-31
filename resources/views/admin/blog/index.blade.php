@extends('layouts.admin')

@section('title', 'Quản lý blog')
@section('page-title', 'Quản lý blog')

@section('content')
<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Thêm bài
    </a>
</div>

<div class="panel-admin rounded border">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tiêu đề</th>
                    <th>ID</th>
                    <th>Ngày đăng</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $post->title }}</div>
                            <div class="small text-muted text-truncate" style="max-width:24rem">{{ $post->excerpt }}</div>
                        </td>
                        <td><code>{{ $post->id }}</code></td>
                        <td class="small">{{ $post->published_at }}</td>
                        <td>
                            @if($post->is_published)
                                <span class="badge text-bg-success">Công khai</span>
                            @else
                                <span class="badge text-bg-secondary">Ẩn</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('blog.show', $post->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <a href="{{ route('admin.blog.edit', $post->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post->id) }}" class="d-inline" onsubmit="return confirm('Xóa bài viết này?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Chưa có bài viết.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
