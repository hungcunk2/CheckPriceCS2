<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExportWriter
{
    /**
     * @param  callable(resource): void  $writer
     */
    public static function download(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  list<string|null>  $row
     */
    public static function row($handle, array $row): void
    {
        fputcsv($handle, array_map(fn ($v) => $v === null ? '' : (string) $v, $row));
    }

    /**
     * @param  resource  $handle
     */
    public static function section($handle, string $title): void
    {
        self::row($handle, []);
        self::row($handle, [$title]);
    }
}
