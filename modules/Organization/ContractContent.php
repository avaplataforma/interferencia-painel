<?php

declare(strict_types=1);

namespace Interferencia\Modules\Organization;

final class ContractContent
{
    private const ALLOWED_TAGS = '<p><div><br><strong><b><em><i><u><h2><h3><ul><ol><li><blockquote><hr><table><thead><tbody><tr><th><td>';

    public static function sanitize(string $content): string
    {
        $content = trim($content);
        if ($content === '') return '';

        $clean = strip_tags($content, self::ALLOWED_TAGS);
        $clean = preg_replace_callback(
            '/<\s*(\/?)\s*([a-z0-9]+)(?:\s[^>]*)?>/i',
            static function (array $match): string {
                $closing = $match[1] === '/';
                $tag = strtolower($match[2]);
                if (!str_contains(self::ALLOWED_TAGS, '<'.$tag.'>')) return '';
                if (in_array($tag, ['br', 'hr'], true)) return '<'.$tag.'>';
                return $closing ? '</'.$tag.'>' : '<'.$tag.'>';
            },
            $clean
        );
        return trim((string) $clean);
    }

    public static function visibleText(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function toHtml(string $content): string
    {
        $content = trim($content);
        if ($content === '') return '';
        if (preg_match('/<(?:p|div|br|strong|b|em|i|u|h2|h3|ul|ol|li|blockquote|hr|table|thead|tbody|tr|th|td)\b/i', $content) === 1) {
            return self::sanitize($content);
        }
        return nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
