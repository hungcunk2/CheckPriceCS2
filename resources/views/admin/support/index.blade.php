@extends('layouts.admin')

@section('title', 'Chat hỗ trợ')
@section('page-title', 'Chat với thành viên')

@section('content')
<div class="panel-admin rounded border">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Thành viên</th>
                    <th>Tin nhắn gần nhất</th>
                    <th>Cập nhật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversations as $conv)
                    @php
                        $last = $conv->lastMessage;
                        $user = $conv->user;
                        $unread = $last && $last->sender === \App\Models\SupportMessage::SENDER_MEMBER
                            && ($conv->admin_last_read_at === null || $last->created_at->gt($conv->admin_last_read_at));
                    @endphp
                    <tr class="{{ $unread ? 'table-warning' : '' }}">
                        <td>
                            <strong>{{ $user?->name ?? '—' }}</strong>
                            <div class="small text-muted">{{ $user?->email }}</div>
                        </td>
                        <td class="small text-truncate" style="max-width:320px">
                            @if($last)
                                {{ Str::limit($last->body, 80) }}
                            @else
                                <span class="text-muted">Chưa có tin nhắn</span>
                            @endif
                        </td>
                        <td class="small text-nowrap">
                            {{ $conv->last_message_at?->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.support.show', $user) }}" class="btn btn-sm btn-primary">
                                Mở chat
                                @if($unread)
                                    <span class="badge text-bg-danger ms-1">Mới</span>
                                @endif
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Chưa có hội thoại nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
