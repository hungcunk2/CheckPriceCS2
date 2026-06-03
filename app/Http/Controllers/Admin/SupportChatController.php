<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
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
        $conversations = SupportConversation::query()
            ->with(['user', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.support.index', [
            'conversations' => $conversations,
            'unreadCount' => $this->chat->unreadCountForAdmin(),
        ]);
    }

    public function show(User $user): View
    {
        $conversation = $this->chat->conversationForUser($user->id);
        $this->chat->markReadByAdmin($conversation);

        $messages = $this->chat->messagesSince($conversation, 0, 200);
        $messages->load('conversation.user');

        return view('admin.support.show', [
            'user' => $user,
            'conversation' => $conversation,
            'initialMessages' => $this->chat->serializeMessages($messages, 'admin'),
        ]);
    }

    public function messages(Request $request, User $user): JsonResponse
    {
        $conversation = $this->chat->conversationForUser($user->id);
        $afterId = max(0, (int) $request->query('after_id', 0));
        $messages = $this->chat->messagesSince($conversation, $afterId);
        $messages->load('conversation.user');

        $this->chat->markReadByAdmin($conversation);

        return response()->json([
            'ok' => true,
            'messages' => $this->chat->serializeMessages($messages, 'admin'),
            'unread_count' => $this->chat->unreadCountForAdmin(),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $adminUsername = (string) session('admin_username', 'Admin');
        $message = $this->chat->postAdminMessage($user, $adminUsername, $validated['body']);
        $message->load('conversation.user');

        return response()->json([
            'ok' => true,
            'message' => $this->chat->serializeMessages(collect([$message]), 'admin')[0],
        ]);
    }
}
