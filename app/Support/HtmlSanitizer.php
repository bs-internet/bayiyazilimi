<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Zengin metin / eski siteden gelen HTML'i beyaz liste ile temizler ve sadeleştirir.
 *
 * - Yalnızca anlamsal etiketler kalır (p, h2-h4, liste, tablo, strong/em, a, img…);
 *   div/span/font/section gibi sarmalayıcılar açılır, <pre> paragrafa çevrilir.
 * - Tüm nitelikler (style, class, id, width…) silinir; yalnızca a[href,target],
 *   img[src,alt], td/th[colspan,rowspan] ve text-align hizalaması kalır.
 * - script/style/iframe/form vb. içerikleriyle birlikte, javascript: bağlantıları ve
 *   boş paragraflar kaldırılır. Excel (OOXML) kaçışları (_x000D_) normalize edilir.
 */
final class HtmlSanitizer
{
    /** Etiket => izinli nitelikler. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'blockquote' => [],
        'a' => ['href', 'target'],
        'img' => ['src', 'alt'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Eşdeğer etikete çevrilenler. */
    private const RENAME = [
        'b' => 'strong', 'i' => 'em', 'strike' => 'strong', 'del' => 's',
        'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'pre' => 'p', 'center' => 'p',
        'dl' => 'ul', 'dt' => 'li', 'dd' => 'li', 'caption' => 'p',
    ];

    /** İçeriğiyle birlikte silinenler. */
    private const DROP = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'textarea', 'select',
        'button', 'input', 'link', 'meta', 'base', 'noscript', 'svg', 'video', 'audio', 'canvas', 'head', 'title',
    ];

    /** Blok düzeyindeki etiketler (div'in paragraf mı yoksa sarmalayıcı mı olduğuna karar vermek için). */
    private const BLOCKS = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'blockquote', 'pre', 'section', 'article', 'hr', 'dl', 'center'];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Excel kaçışları: _x000D_ (CR), _x0009_ (TAB) vb.
        $html = preg_replace('/_x000[dDaA]_/', "\n", $html) ?? $html;
        $html = preg_replace('/_x[0-9a-fA-F]{4}_/', ' ', $html) ?? $html;

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root);
        self::removeEmpty($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        // Fazla boşluk ve ardışık <br>'leri sadeleştir.
        $out = preg_replace('/(<br>\s*){3,}/', '<br><br>', $out) ?? $out;
        $out = preg_replace('/<p>\s*(<br>\s*)+/', '<p>', $out) ?? $out;
        $out = preg_replace('/(\s*<br>)+\s*<\/p>/', '</p>', $out) ?? $out;
        $out = preg_replace('/[ \t]{2,}/', ' ', $out) ?? $out;

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            // Önce alt ağacı işle (div kararı sadeleşmiş çocuklara göre verilir).
            self::walk($child);

            if ($tag === 'pre') {
                self::preToParagraph($child);
            }

            if ($tag === 'div') {
                $tag = self::hasBlockChild($child) ? 'unwrap' : 'p';
            }

            if (isset(self::RENAME[$tag])) {
                $tag = self::RENAME[$tag];
            }

            if ($tag === 'unwrap' || ! isset(self::ALLOWED[$tag])) {
                self::unwrap($child);

                continue;
            }

            $element = strtolower($child->tagName) === $tag ? $child : self::rename($child, $tag);
            self::filterAttributes($element, $tag);
        }
    }

    private static function hasBlockChild(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), self::BLOCKS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * <pre> içindeki satır sonlarını <br>'ye çevirir (paragraf olarak gösterilecek).
     */
    private static function preToParagraph(DOMElement $pre): void
    {
        foreach (iterator_to_array($pre->childNodes) as $child) {
            if (! $child instanceof DOMText || ! str_contains($child->data, "\n")) {
                continue;
            }

            $lines = preg_split('/\r?\n/', $child->data) ?: [];
            $fragment = $pre->ownerDocument->createDocumentFragment();

            foreach ($lines as $i => $line) {
                if ($i > 0) {
                    $fragment->appendChild($pre->ownerDocument->createElement('br'));
                }

                $fragment->appendChild($pre->ownerDocument->createTextNode($line));
            }

            $pre->replaceChild($fragment, $child);
        }
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        // Blok sarmalayıcı açılırken metin bitişmesin.
        $isBlock = in_array(strtolower($element->tagName), self::BLOCKS, true);

        if ($isBlock) {
            $parent->insertBefore($element->ownerDocument->createTextNode(' '), $element);
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        if ($isBlock) {
            $parent->insertBefore($element->ownerDocument->createTextNode(' '), $element);
        }

        $parent->removeChild($element);
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $new = $element->ownerDocument->createElement($tag);

        while ($element->firstChild !== null) {
            $new->appendChild($element->firstChild);
        }

        // Yalnızca sonradan süzülecek geçerli adlı nitelikler taşınır (bozuk HTML'de ".." gibi adlar olabilir).
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (preg_match('/^[a-z][a-z0-9-]*$/i', $attribute->name) === 1) {
                $new->setAttribute($attribute->name, $attribute->value);
            }
        }

        $element->parentNode?->replaceChild($new, $element);

        return $new;
    }

    private static function filterAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        $align = null;

        if (preg_match('/text-align\s*:\s*(left|right|center|justify)/i', $element->getAttribute('style'), $m) === 1) {
            $align = strtolower($m[1]);
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($align !== null && $align !== 'left' && in_array($tag, ['p', 'h2', 'h3', 'h4', 'td', 'th'], true)) {
            $element->setAttribute('style', 'text-align: '.$align);
        }

        foreach (['href', 'src'] as $attr) {
            if (! $element->hasAttribute($attr)) {
                continue;
            }

            $value = trim($element->getAttribute($attr));

            if (! preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $value)) {
                if ($tag === 'img') {
                    $element->parentNode?->removeChild($element);

                    return;
                }

                $element->removeAttribute($attr);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } elseif ($tag === 'a') {
            $element->removeAttribute('target');
        }
    }

    /**
     * İçi boş paragraf/başlık/liste öğelerini kaldırır (yalnızca boşluk, &nbsp; ya da <br>).
     */
    private static function removeEmpty(DOMElement $root): void
    {
        $xpath = new \DOMXPath($root->ownerDocument);

        foreach (['p', 'h2', 'h3', 'h4', 'li', 'strong', 'em', 'u', 'ul', 'ol', 'blockquote'] as $tag) {
            foreach (iterator_to_array($xpath->query('.//'.$tag, $root) ?: []) as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $text = trim(str_replace("\xC2\xA0", ' ', $node->textContent));

                if ($text === '' && $xpath->query('.//img|.//table', $node)?->length === 0) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }
    }

    /**
     * Yalnızca Google Haritalar embed adresini kabul eder (iframe HTML'i ya da doğrudan src).
     */
    public static function mapEmbedUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $src = trim($value);

        if (preg_match('#src\s*=\s*["\']([^"\']+)["\']#i', $src, $m) === 1) {
            $src = html_entity_decode($m[1]);
        }

        $host = parse_url($src, PHP_URL_HOST);
        $scheme = parse_url($src, PHP_URL_SCHEME);

        if ($scheme !== 'https' || ! is_string($host)) {
            return null;
        }

        $allowed = in_array($host, ['www.google.com', 'google.com', 'maps.google.com', 'www.google.com.tr'], true);

        return $allowed && str_contains($src, '/maps') ? $src : null;
    }
}
