<?php

namespace App\Support;

class BlogContent
{
    public static function isHtml(string $content): bool
    {
        return preg_match('/<(?:p|h[1-6]|ul|ol|li|strong|em|br|div|table|img|a|blockquote)\b/i', $content) === 1;
    }

    public static function forEditor(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $html = self::isHtml($content) ? $content : self::markdownToHtml($content);

        return str_replace('</textarea>', '&lt;/textarea&gt;', $html);
    }

    public static function toHtml(string $content): string
    {
        if (self::isHtml($content)) {
            return self::sanitizeHtml($content);
        }

        return self::markdownToHtml($content);
    }

    private static function sanitizeHtml(string $html): string
    {
        $anchors = [];
        $html = preg_replace_callback(
            '/<a\s([^>]*?)>(.*?)<\/a>/is',
            function (array $matches) use (&$anchors) {
                $key = '__BLOG_LINK_'.count($anchors).'__';
                $anchors[$key] = self::buildSafeAnchor($matches[1], $matches[2]);

                return $key;
            },
            $html
        ) ?? $html;

        $allowed = '<p><br><hr><h1><h2><h3><h4><h5><h6><ul><ol><li><strong><b><em><i><u><s><a><img><table><thead><tbody><tr><th><td><blockquote><pre><code><span><div>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace_callback(
            '/\sstyle=(["\'])(.*?)\1/i',
            fn (array $matches) => self::sanitizeStyleAttribute($matches[2]),
            $html
        ) ?? $html;
        $html = preg_replace('/\s(on\w+)=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        foreach ($anchors as $key => $anchor) {
            $html = str_replace($key, $anchor, $html);
        }

        return $html;
    }

    private static function buildSafeAnchor(string $attributes, string $innerHtml): string
    {
        $href = self::extractAttribute($attributes, 'href');
        if ($href === '' || preg_match('/^\s*javascript:/i', $href)) {
            return strip_tags($innerHtml, '<strong><b><em><i><u><s><span>');
        }

        if (! preg_match('/^https?:\/\//i', $href) && ! str_starts_with($href, '/')) {
            return strip_tags($innerHtml, '<strong><b><em><i><u><s><span>');
        }

        $class = self::extractAttribute($attributes, 'class');
        $classAttr = str_contains($class, 'lp-link-hidden') ? ' class="lp-link-hidden"' : '';

        $target = preg_match('/^https?:\/\//i', $href)
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        $label = strip_tags($innerHtml, '<strong><b><em><i><u><s><span>');

        return '<a href="'.e($href).'"'.$classAttr.$target.'>'.$label.'</a>';
    }

    private static function extractAttribute(string $attributes, string $name): string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'=(["\'])(.*?)\1/i', $attributes, $matches)) {
            return trim($matches[2]);
        }

        return '';
    }

    private static function sanitizeStyleAttribute(string $style): string
    {
        $allowed = [];

        foreach (explode(';', $style) as $rule) {
            $rule = trim($rule);
            if ($rule === '' || ! str_contains($rule, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $rule, 2));
            $property = strtolower($property);

            if (! in_array($property, ['font-family', 'font-size', 'font-weight', 'text-align', 'line-height'], true)) {
                continue;
            }

            if (preg_match('/url\s*\(|expression\s*\(|javascript:/i', $value)) {
                continue;
            }

            if ($property === 'font-weight' && ! preg_match('/^(400|700|normal|bold)$/i', $value)) {
                continue;
            }

            $allowed[] = $property.': '.$value;
        }

        if ($allowed === []) {
            return '';
        }

        return ' style="'.e(implode('; ', $allowed)).'"';
    }

    private static function markdownToHtml(string $content): string
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
