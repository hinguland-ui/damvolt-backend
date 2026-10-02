<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for the legal pages (rich-text editor output).
 * Only basic formatting tags survive; every attribute is dropped except a safe <a href>.
 */
class HtmlSanitizer
{
    private const ALLOWED = ['p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i', 'u', 'blockquote'];

    /** Tags removed together with everything inside them (unknown harmless tags are just unwrapped). */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'head', 'title'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div data-root="1">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = null;
        foreach ($dom->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('data-root') === '1') {
                $root = $div;
                break;
            }
        }
        if (! $root) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $node->removeChild($child);          // comments, processing instructions, CDATA …
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! in_array($tag, self::ALLOWED, true)) {
                while ($child->firstChild) {              // unwrap: keep the text, lose the tag
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $href = $tag === 'a' ? $child->getAttribute('href') : null;
            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttributeNode($attr);
            }

            if ($tag === 'a' && self::safeUrl($href)) {
                $child->setAttribute('href', trim($href));
                if (preg_match('#^https?://#i', trim($href))) {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    }

    /** http(s), mailto, tel, same-site paths and #anchors only — nothing like javascript: or data:. */
    public static function safeUrl(?string $url): bool
    {
        $url = preg_replace('/[\x00-\x20\x7f-\x9f]+/u', '', (string) $url) ?? '';

        return $url !== '' && (bool) preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $url);
    }
}
