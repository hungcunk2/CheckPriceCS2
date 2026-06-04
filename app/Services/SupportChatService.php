<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class SupportChatService
{
    public function __construct(
        private SupportChatAttachmentStorage $attachments,
    ) {}

    public function conversationForUser(int $userId): SupportConversation
    {
        return SupportConversation::query()->firstOrCreate(['user_id' => $userId]);
    }

    public function postMemberMessage(User $user, ?string $body, ?UploadedFile $image = null): SupportMessage
    {
        $conversation = $this->conversationForUser($user->id);
        $message = $this->createMessage($conversation, SupportMessage::SENDER_MEMBER, null, $body, $image);
        $conversation->update([
            'last_message_at' => $message->created_at,
            'member_last_read_at' => $message->created_at,
        ]);

        return $message;
    }

    public function postAdminMessage(User $user, string $adminUsername, ?string $body, ?UploadedFile $image = null): SupportMessage
    {
        $conversation = $this->conversationForUser($user->id);
        $message = $this->createMessage($conversation, SupportMessage::SENDER_ADMIN, $adminUsername, $body, $image);
        $conversation->update([
            'last_message_at' => $message->created_at,
            'admin_last_read_at' => $message->created_at,
        ]);

        return $message;
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    public function messagesSince(SupportConversation $conversation, int $afterId = 0, int $limit = 100): Collection
    {
        $q = $conversation->messages()->orderBy('id');
        if ($afterId > 0) {
            $q->where('id', '>', $afterId);
        }

        return $q->limit($limit)->get();
    }

    public function markReadByMember(SupportConversation $conversation): void
    {
        $conversation->update(['member_last_read_at' => now()]);
    }

    public function markReadByAdmin(SupportConversation $conversation): void
    {
        $conversation->update(['admin_last_read_at' => now()]);
    }

    public function unreadCountForAdmin(): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        try {
            return (int) SupportConversation::query()
                ->whereExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('support_messages')
                        ->whereColumn('support_messages.support_conversation_id', 'support_conversations.id')
                        ->where('support_messages.sender', SupportMessage::SENDER_MEMBER)
                        ->where(function ($inner) {
                            $inner->whereNull('support_conversations.admin_last_read_at')
                                ->orWhereColumn('support_messages.created_at', '>', 'support_conversations.admin_last_read_at');
                        });
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function unreadCountForMember(User $user): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        try {
            $conversation = SupportConversation::query()->where('user_id', $user->id)->first();
            if ($conversation === null) {
                return 0;
            }

            $since = $conversation->member_last_read_at;

            return (int) $conversation->messages()
                ->where('sender', SupportMessage::SENDER_ADMIN)
                ->when($since !== null, fn ($q) => $q->where('created_at', '>', $since))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function tablesReady(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('support_conversations')
            && \Illuminate\Support\Facades\Schema::hasTable('support_messages');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeMessages(Collection $messages, string $viewer): array
    {
        return $messages->map(function (SupportMessage $m) use ($viewer) {
            $isMine = ($viewer === 'member' && $m->sender === SupportMessage::SENDER_MEMBER)
                || ($viewer === 'admin' && $m->sender === SupportMessage::SENDER_ADMIN);

            $label = $m->sender === SupportMessage::SENDER_ADMIN
                ? ($m->admin_username ?: 'Admin')
                : ($m->conversation->user->name ?? 'Thành viên');

            return [
                'id' => $m->id,
                'sender' => $m->sender,
                'sender_label' => $label,
                'body' => $m->body ?? '',
                'image_url' => $this->attachmentUrl($m, $viewer),
                'created_at' => $m->created_at?->timezone(config('cs2price.timezone'))->format('d/m/Y H:i'),
                'is_mine' => $isMine,
            ];
        })->values()->all();
    }

    public function memberCanViewMessage(User $user, SupportMessage $message): bool
    {
        $message->loadMissing('conversation');

        return (int) ($message->conversation?->user_id ?? 0) === (int) $user->id;
    }

    private function attachmentUrl(SupportMessage $message, string $viewer): ?string
    {
        if (! $message->hasAttachment() || ! $this->attachments->hasAttachment($message)) {
            return null;
        }

        return $viewer === 'admin'
            ? route('admin.support.attachment', $message)
            : route('member.support.attachment', $message);
    }

    private function createMessage(
        SupportConversation $conversation,
        string $sender,
        ?string $adminUsername,
        ?string $body,
        ?UploadedFile $image = null,
    ): SupportMessage {
        $text = trim((string) $body);
        $attachmentPath = null;
        $attachmentMime = null;

        if ($image !== null) {
            $stored = $this->attachments->store($conversation, $image);
            $attachmentPath = $stored['path'];
            $attachmentMime = $stored['mime'];
        }

        if ($text === '' && $attachmentPath === null) {
            throw new RuntimeException('Tin nhắn trống.');
        }

        return SupportMessage::query()->create([
            'support_conversation_id' => $conversation->id,
            'sender' => $sender,
            'admin_username' => $adminUsername,
            'body' => $text !== '' ? $text : null,
            'attachment_path' => $attachmentPath,
            'attachment_mime' => $attachmentMime,
        ]);
    }
}
