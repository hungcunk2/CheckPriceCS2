@extends('layouts.member')

@section('title', 'Chat hỗ trợ')
@section('page-title', 'Chat với Admin')

@section('content')
@include('partials.support-chat', [
    'chatMessagesUrl' => route('member.support.messages'),
    'chatPostUrl' => route('member.support.store'),
    'initialMessages' => $initialMessages,
    'chatViewer' => 'member',
    'chatTitle' => 'Chat với Admin',
])
@endsection
