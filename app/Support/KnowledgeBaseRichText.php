<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class KnowledgeBaseRichText
{
    /**
     * @var array<string, string>
     */
    private const array TAG_ALIASES = [
        'b' => 'strong',
        'i' => 'em',
        'strike' => 's',
        'del' => 's',
    ];

    /**
     * @var array<int, string>
     */
    private const array INLINE_TAGS = [
        'strong',
        'em',
        'u',
        's',
        'a',
        'br',
        'code',
    ];

    public function sanitize(?string $value): ?string
    {
        $normalized = $this->normalizeInput($value);

        if ($normalized === null) {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$normalized.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return null;
        }

        $sanitized = $this->trimBreaks($this->sanitizeChildren($root));

        return $this->plainText($sanitized) !== ''
            ? $sanitized
            : null;
    }

    public function plainText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $value);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function sanitizeChildren(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $this->sanitizeNode($childNode);
        }

        return $html;
    }

    private function sanitizeNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tagName = $this->normalizeTagName($node->tagName);

        if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
            return '';
        }

        if (in_array($tagName, ['div', 'p'], true)) {
            $content = $this->trimBreaks($this->sanitizeChildren($node));

            return $content === '' ? '' : $content.'<br>';
        }

        if (! in_array($tagName, self::INLINE_TAGS, true)) {
            return $this->sanitizeChildren($node);
        }

        if ($tagName === 'br') {
            return '<br>';
        }

        $content = $this->sanitizeChildren($node);

        if ($content === '') {
            return '';
        }

        if ($tagName === 'a') {
            $href = $this->sanitizeHref($node->getAttribute('href'));

            return $href === null
                ? $content
                : '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.$content.'</a>';
        }

        return sprintf('<%1$s>%2$s</%1$s>', $tagName, $content);
    }

    private function normalizeInput(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace('&nbsp;', ' ', $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeTagName(string $tagName): string
    {
        $normalized = strtolower($tagName);

        return self::TAG_ALIASES[$normalized] ?? $normalized;
    }

    private function sanitizeHref(?string $href): ?string
    {
        if (! is_string($href)) {
            return null;
        }

        $normalized = trim($href);

        if ($normalized === '') {
            return null;
        }

        if (
            str_starts_with($normalized, '/')
            || str_starts_with($normalized, '#')
            || str_starts_with($normalized, '?')
        ) {
            return $normalized;
        }

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true)
            ? $normalized
            : null;
    }

    private function trimBreaks(string $value): string
    {
        $trimmed = preg_replace('/^(?:\s*<br>\s*)+|(?:\s*<br>\s*)+$/i', '', trim($value)) ?? trim($value);

        return preg_replace('/(?:\s*<br>\s*){3,}/i', '<br><br>', $trimmed) ?? $trimmed;
    }
}
