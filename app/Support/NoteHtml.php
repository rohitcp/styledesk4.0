<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * The only place a note's rich text is allowed in from the browser.
 *
 * A rich text editor posts HTML, and HTML from a browser is a string a person
 * chose — the editor's own toolbar is not a constraint on what arrives, only
 * on what is convenient to produce. Anything that reaches the database here is
 * later rendered unescaped on a colleague's screen, so this is the boundary
 * that decides whether a note can carry a script.
 *
 * Written as an allowlist, never a blocklist: unknown tags are unwrapped and
 * unknown attributes dropped, so a tag nobody thought of arrives as its own
 * text rather than as behaviour. The list is deliberately the MVP toolbar and
 * nothing more.
 */
class NoteHtml
{
    /**
     * Tags a note may contain, and the attributes each may carry.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h3' => [],
        'a' => ['href'],
        'img' => ['src', 'alt'],
    ];

    /**
     * Where a note's images may come from.
     *
     * Two, because there are two eras of them: /files/ is the storage
     * component's own route, and the older path is where note images lived
     * before it existed. Notes written then must keep their pictures.
     *
     * @var list<string>
     */
    private const IMAGE_ROOTS = ['/files/', '/storage/brand/notes/', '/storage/tenants/'];

    /** Schemes a link may use. javascript: is the reason this list exists. */
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Clean markup, or an empty string if there was nothing in it.
     */
    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;

        /* Errors suppressed rather than trusted: the parser complains about
           HTML5 tags it does not know, and a note must not fail to save
           because of a warning about <figure>. */
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        self::scrub($body);

        $clean = '';

        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return self::isEmpty($clean) ? '' : trim($clean);
    }

    /**
     * Whether a note's markup says anything at all.
     *
     * An editor that has been focused and left alone posts "<p></p>", which
     * is not a note. Asked before saving, so an empty note is refused by
     * validation rather than stored as a blank row.
     */
    public static function isEmpty(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        // An image is content even though it contributes no text.
        if (Str::contains($html, '<img')) {
            return false;
        }

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) === '';
    }

    /** The plain reading of a note, for anywhere markup would be noise. */
    public static function toText(?string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/li|\/h3)[^>]*>/i', "\n", (string) $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Walk the tree, keeping what is allowed and unwrapping the rest.
     *
     * Unwrapping rather than deleting: a <div> around a paragraph is not an
     * attack, and throwing away its contents would lose what someone wrote.
     * A <script>, by contrast, has nothing worth keeping — its text *is* the
     * payload — so those go entirely.
     */
    private static function scrub(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = mb_strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                self::scrub($child);

                if (! array_key_exists($tag, self::ALLOWED)) {
                    self::unwrap($child);

                    continue;
                }

                self::scrubAttributes($child, $tag);

                continue;
            }

            /* Comments can carry markup that some parsers later re-read as
               tags, and a note has no use for them either way. */
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function scrubAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(mb_strtolower($attribute->nodeName), self::ALLOWED[$tag], true)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a') {
            self::scrubLink($element);
        }

        if ($tag === 'img') {
            self::scrubImage($element);
        }
    }

    private static function scrubLink(DOMElement $element): void
    {
        $href = trim($element->getAttribute('href'));
        $scheme = mb_strtolower((string) parse_url($href, PHP_URL_SCHEME));

        // Relative links are fine; anything with a scheme must use one of ours.
        if ($scheme !== '' && ! in_array($scheme, self::SCHEMES, true)) {
            $element->removeAttribute('href');

            return;
        }

        /* Someone else's site opens in its own tab, and never with a handle
           on ours: noopener is what stops the opened page reaching back
           through window.opener. */
        $element->setAttribute('target', '_blank');
        $element->setAttribute('rel', 'noopener noreferrer');
    }

    /**
     * Images must be ones this app stored.
     *
     * A note that could embed a remote URL would call that server every time
     * a colleague opened the client — telling whoever runs it who read which
     * client's notes, and when.
     */
    private static function scrubImage(DOMElement $element): void
    {
        $src = trim($element->getAttribute('src'));

        /**
         * The upload endpoint answers with an absolute URL, so the src that
         * arrives is this app's own host — but what gets stored is the path
         * alone. A note written today should still show its picture after
         * the business moves to its own domain, and an absolute URL in the
         * database would be pointing at the old one.
         */
        $host = rtrim((string) config('app.url'), '/');

        if ($host !== '' && Str::startsWith($src, $host.'/')) {
            $src = Str::after($src, $host);
        }

        if (! Str::startsWith($src, self::IMAGE_ROOTS)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        $element->setAttribute('src', $src);
    }

    /** Replace an element with its children. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
