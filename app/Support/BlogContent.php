<?php

namespace App\Support;

class BlogContent
{
    public static function toHtml(string $content): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $parts = [];
        $listType = null;

        $closeList = function () use (&$parts, &$listType): void {
            if ($listType !== null) {
                $parts[] = '</'.$listType.'>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            if (str_starts_with($line, '## ')) {
                $closeList();
                $parts[] = '<h2>'.e(substr($line, 3)).'</h2>';
                continue;
            }

            if (str_starts_with($line, '### ')) {
                $closeList();
                $parts[] = '<h3>'.e(substr($line, 4)).'</h3>';
                continue;
            }

            if (str_starts_with($line, '- ')) {
                if ($listType !== 'ul') {
                    $closeList();
                    $listType = 'ul';
                    $parts[] = '<ul>';
                }
                $parts[] = '<li>'.self::inline($line, 2).'</li>';
                continue;
            }

            if (preg_match('/^\d+\. /', $line)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $listType = 'ol';
                    $parts[] = '<ol>';
                }
                $parts[] = '<li>'.self::inline(preg_replace('/^\d+\. /', '', $line) ?? '', 0).'</li>';
                continue;
            }

            if (str_starts_with($line, '| ') && str_ends_with($line, ' |')) {
                $closeList();
                if (str_contains($line, '---')) {
                    continue;
                }
                $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn ($c) => $c !== ''));
                $parts[] = '<div class="lp-blog-table-row">';
                foreach ($cells as $index => $cell) {
                    $class = $index === 0 ? 'lp-blog-table-cell lp-blog-table-cell--label' : 'lp-blog-table-cell';
                    $parts[] = '<span class="'.$class.'">'.e($cell).'</span>';
                }
                $parts[] = '</div>';
                continue;
            }

            if (preg_match('/^\*\*(.+)\*\*$/', $line, $matches)) {
                $closeList();
                $parts[] = '<p class="lp-blog-bold">'.e($matches[1]).'</p>';
                continue;
            }

            if (str_starts_with($line, 'Giá: ')) {
                $closeList();
                $parts[] = '<p class="lp-blog-price">'.e($line).'</p>';
                continue;
            }

            if (trim($line) === '') {
                $closeList();
                $parts[] = '<div class="lp-blog-spacer"></div>';
                continue;
            }

            $closeList();
            $parts[] = '<p>'.self::inline($line, 0).'</p>';
        }

        $closeList();

        return implode("\n", $parts);
    }

    private static function inline(string $text, int $offset): string
    {
        $text = $offset > 0 ? substr($text, $offset) : $text;

        return preg_replace_callback(
            '/\*\*(.+?)\*\*/',
            fn (array $matches) => '<strong>'.e($matches[1]).'</strong>',
            e($text)
        ) ?? e($text);
    }
}
