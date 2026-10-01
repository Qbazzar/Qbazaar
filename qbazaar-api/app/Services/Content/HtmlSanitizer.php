<?php

declare(strict_types=1);

namespace App\Services\Content;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Reduces staff-authored rich text (CMS pages, help articles) to a small
 * allowlist of formatting tags before it is stored, because the web renders
 * those bodies as raw HTML. Parsing uses the HTML5 parser so the tree we
 * clean matches what a browser would build from the same markup.
 */
class HtmlSanitizer
{
    /** Tags removed together with everything inside them. */
    private const array DROPPED_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'template', 'noscript', 'noembed', 'noframes', 'svg', 'math', 'form', 'textarea',
        'select', 'button', 'title', 'head', 'meta', 'link', 'base',
    ];

    /** @var array<string, list<string>> tag => attributes it may keep */
    private const array ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'small' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'blockquote' => [], 'code' => [], 'pre' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    private const array GLOBAL_ATTRIBUTES = ['dir', 'lang'];

    private const array URL_ATTRIBUTES = ['href', 'src'];

    private const array ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
        );

        $body = $document->body;

        if ($body === null) {
            return '';
        }

        $this->cleanChildren($body);

        return trim($body->innerHTML);
    }

    private function cleanChildren(Node $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof Element) {
                $this->cleanElement($child);
            } elseif (! $child instanceof Text) {
                $parent->removeChild($child);
            }
        }
    }

    private function cleanElement(Element $element): void
    {
        $tag = strtolower($element->localName);

        if (in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
            $element->remove();

            return;
        }

        $this->cleanChildren($element);

        if (! array_key_exists($tag, self::ALLOWED)) {
            $element->replaceWith(...iterator_to_array($element->childNodes));

            return;
        }

        $this->cleanAttributes($element, self::ALLOWED[$tag]);

        if ($tag === 'a' && $element->hasAttribute('target')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * @param list<string> $allowedForTag
     */
    private function cleanAttributes(Element $element, array $allowedForTag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $allowed = in_array($name, $allowedForTag, true) || in_array($name, self::GLOBAL_ATTRIBUTES, true);

            if (! $allowed || (in_array($name, self::URL_ATTRIBUTES, true) && ! $this->isSafeUrl($attribute->value))) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    private function isSafeUrl(string $url): bool
    {
        // Browsers ignore whitespace and control characters inside a scheme,
        // so "java\tscript:" must be judged as "javascript:".
        $normalised = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalised, $matches) !== 1) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }
}
