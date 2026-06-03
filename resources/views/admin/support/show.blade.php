@extends('layouts.admin')

@section('title', 'Chat — '.$user->name)
@section('page-title', 'Chat: '.$user->name)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.support.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Danh sách chat
    </a>
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary btn-sm">Sửa user</a>
    <span class="small text-muted ms-2">{{ $user->email }}</span>
</div>

@include('partials.support-chat', [
    'chatMessagesUrl' => route('admin.support.messages', $user),
    'chatPostUrl' => route('admin.support.store', $user),
    'initialMessages' => $initialMessages,
    'chatViewer' => 'admin',
    'chatTitle' => $user->name,
])
@endsection
