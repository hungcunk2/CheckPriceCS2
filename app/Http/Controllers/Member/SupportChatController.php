<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
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
            'body' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('image')) {
            return response()->json(['ok' => false, 'message' => 'Nhập tin nhắn hoặc chọn ảnh.'], 422);
        }

        try {
            $user = auth()->user();
            $message = $this->chat->postMemberMessage($user, $body !== '' ? $body : null, $request->file('image'));
            $message->load('conversation.user');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->getMessage() ?: 'Không gửi được tin nhắn.'], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $this->chat->serializeMessages(collect([$message]), 'member')[0],
        ]);
    }

    public function attachment(SupportMessage $message): Response
    {
        $user = auth()->user();
        if (! $this->chat->memberCanViewMessage($user, $message)) {
            abort(404);
        }

        return $this->attachments->stream($message);
    }
}
