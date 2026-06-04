<?php

namespace App\Support;

use Throwable;

class AdminFacingError
{
    public static function message(Throwable $e, string $fallback): string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        $raw = $e->getMessage();

        if (self::isStoragePermissionError($raw)) {
            return 'Không ghi được storage/cache (quyền file). Trên VPS chạy: bash /var/www/checkpricecs2/deploy/fix-storage-permissions.sh';
        }

        if (str_contains($raw, 'SQLSTATE') && str_contains($raw, 'empire_proxy_settings')) {
            return 'Thiếu bảng empire_proxy_settings. Trên VPS chạy: php artisan migrate --force';
        }

        return $fallback;
    }

    private static function isStoragePermissionError(string $message): bool
    {
        if (! str_contains($message, 'Permission denied')) {
            return false;
        }

        return str_contains($message, 'storage/')
            || str_contains($message, 'storage\\')
            || str_contains($message, 'bootstrap/cache');
    }
}
