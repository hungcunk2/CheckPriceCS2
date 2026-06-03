<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function __construct(
        private SupportChatService $chat,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $conversation = $this->chat->conversationForUser($user->id);
        $this->chat->markReadByMember($conversation);

        $messages = $this->chat->messagesSince($conversation, 0, 200);
        $messages->load('conversation.user');

        return view('member.support.index', [
            'conversation' => $conversation,
            'initialMessages' => $this->chat->serializeMessages($messages, 'member'),
            'unreadCount' => 0,
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $user = auth()->user();
        $conversation = $this->chat->conversationForUser($user->id);

        $afterId = max(0, (int) $request->query('after_id', 0));
        $messages = $this->chat->messagesSince($conversation, $afterId);
        $messages->load('conversation.user');

        $this->chat->markReadByMember($conversation);

        return response()->json([
            'ok' => true,
            'messages' => $this->chat->serializeMessages($messages, 'member'),
            'unread_count' => $this->chat->unreadCountForMember($user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $user = auth()->user();
        $message = $this->chat->postMemberMessage($user, $validated['body']);
        $message->load('conversation.user');

        return response()->json([
            'ok' => true,
            'message' => $this->chat->serializeMessages(collect([$message]), 'member')[0],
        ]);
    }
}
