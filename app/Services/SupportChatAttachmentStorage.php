<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportChatAttachmentStorage
{
    private const DISK = 'local';

    private const DIR = 'support-chat';

    /**
     * @return array{path: string, mime: string}
     */
    public function store(SupportConversation $conversation, UploadedFile $file): array
    {
        $ext = strtolower($file->extension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            throw new RuntimeException('Định dạng ảnh không hỗ trợ.');
        }

        $filename = Str::uuid()->toString().'.'.$ext;
        $path = $file->storeAs(self::DIR.'/'.$conversation->id, $filename, self::DISK);

        if ($path === false) {
            throw new RuntimeException('Không lưu được ảnh.');
        }

        return [
            'path' => $path,
            'mime' => $file->getMimeType() ?: 'image/jpeg',
        ];
    }

    public function stream(SupportMessage $message): StreamedResponse|Response
    {
        $path = trim((string) ($message->attachment_path ?? ''));
        if ($path === '' || ! Storage::disk(self::DISK)->exists($path)) {
            return response('Not found', 404);
        }

        $mime = trim((string) ($message->attachment_mime ?? ''));
        if ($mime === '') {
            $mime = 'image/jpeg';
        }

        return Storage::disk(self::DISK)->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function hasAttachment(SupportMessage $message): bool
    {
        $path = trim((string) ($message->attachment_path ?? ''));

        return $path !== '' && Storage::disk(self::DISK)->exists($path);
    }
}
