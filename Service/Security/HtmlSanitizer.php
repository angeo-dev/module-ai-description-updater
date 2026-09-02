<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service\Security;

/**
 * Whitelist-based HTML sanitizer for AI-generated content.
 *
 * AI output is untrusted input: a prompt-injection in the product name (or a
 * misbehaving model) can return <script> / event-handler markup that would
 * otherwise be stored as the product description and rendered on the
 * storefront (stored XSS). This sanitizer keeps only a safe subset of
 * formatting tags and strips every attribute except a vetted few.
 */
class HtmlSanitizer
{
    /** Tags allowed in generated product descriptions. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's', 'span',
        'h2', 'h3', 'h4', 'h5',
        'a', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div',
    ];

    /** Attributes allowed per tag. Everything else is removed. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    /** URL schemes allowed in href values. */
    private const ALLOWED_URL_SCHEMES = ['http', 'https'];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Wrap so DOMDocument treats the fragment as UTF-8 body content.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="angeo-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = $dom->getElementById('angeo-root');
        if ($root === null) {
            // Parsing failed entirely — fall back to plain text.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $this->sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        // Iterate over a static copy: we mutate the child list while walking it.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMComment
                || $child instanceof \DOMProcessingInstruction
                || $child instanceof \DOMDocumentType
            ) {
                $node->removeChild($child);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue; // text nodes are safe as-is
            }

            $tag = strtolower($child->tagName);

            // Dangerous containers: drop the element AND its content.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown tag: unwrap it but keep (sanitized) children.
                $this->sanitizeNode($child);
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);
            $this->sanitizeNode($child);
        }
    }

    private function sanitizeAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        $toRemove = [];
        foreach ($element->attributes as $attribute) {
            /** @var \DOMAttr $attribute */
            $name = strtolower($attribute->name);

            if (!in_array($name, $allowed, true)) {
                $toRemove[] = $attribute->name;
                continue;
            }

            if ($name === 'href' && !$this->isSafeUrl($attribute->value)) {
                $toRemove[] = $attribute->name;
            }
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true; // fragment / relative
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === '') {
            return true; // scheme-less relative URL
        }

        return in_array($scheme, self::ALLOWED_URL_SCHEMES, true);
    }
}
