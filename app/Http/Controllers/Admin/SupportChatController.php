<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\SupportMessage;
use App\Services\SupportChatAttachmentStorage;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SupportChatController extends Controller
{
    public function __construct(
        private SupportChatService $chat,
        private SupportChatAttachmentStorage $attachments,
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
            'body' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('image')) {
            return response()->json(['ok' => false, 'message' => 'Nhập tin nhắn hoặc chọn ảnh.'], 422);
        }

        try {
            $adminUsername = (string) session('admin_username', 'Admin');
            $message = $this->chat->postAdminMessage($user, $adminUsername, $body !== '' ? $body : null, $request->file('image'));
            $message->load('conversation.user');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Không gửi được tin nhắn.'], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $this->chat->serializeMessages(collect([$message]), 'admin')[0],
        ]);
    }

    public function attachment(SupportMessage $message): Response
    {
        return $this->attachments->stream($message);
    }
}
