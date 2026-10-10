<?php
namespace OCA\Appointments\Backend;

final class HtmlTemplate
{
    private const ALLOWED = ['a','b','blockquote','br','code','div','em','h1','h2','h3','h4','hr','i','img','li','ol','p','pre','span','strong','u','ul'];
    private const DROP = ['script','style','iframe','object','embed','form','input','button','svg','math','link','meta','base','video','audio','source','textarea','select'];

    public static function render(string $html, array $vars = []): string
    {
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{' . $key . '}}'] = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        // Replace in one pass: user input containing {{otherPlaceholder}} must not
        // trigger a second interpolation.
        return self::sanitize(strtr($html, $replacements));
    }

    /**
     * Split custom email HTML around a single native action-button position.
     * Additional occurrences are ignored to prevent duplicate action links.
     * Splitting happens before variable interpolation, so names and other
     * attendee-supplied values can never produce executable button markers.
     *
     * @return array{string, string}|null
     */
    public static function splitOnConfirmationButtons(string $html): ?array
    {
        $marker = '{{confirmationButtons}}';
        if (!str_contains($html, $marker)) {
            return null;
        }
        [$before, $after] = explode($marker, $html, 2);
        return [$before, str_replace($marker, '', $after)];
    }

    /**
     * Return HTTPS image origins referenced by sanitized HTML. Nextcloud must
     * allow these specific hosts in the booking page's img-src CSP.
     *
     * @return string[]
     */
    public static function imageOrigins(string $html): array
    {
        $safe = self::sanitize($html);
        if ($safe === '' || !preg_match_all('~<img\b[^>]*\bsrc="([^"]+)"~i', $safe, $matches)) {
            return [];
        }
        $origins = [];
        foreach ($matches[1] as $src) {
            $parts = parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (is_array($parts)
                && strtolower($parts['scheme'] ?? '') === 'https'
                && !empty($parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $origins['https://' . $parts['host'] . $port] = true;
            }
        }
        return array_keys($origins);
    }

    private static function safeImageSource(string $src): bool
    {
        if ($src === '' || strlen($src) > 2048 || preg_match('/[\x00-\x20\x7F\\\\]/', $src)) {
            return false;
        }
        // Local absolute paths are allowed, but not protocol-relative URLs.
        if (str_starts_with($src, '/')) {
            return !str_starts_with($src, '//');
        }
        $parts = parse_url($src);
        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    public static function sanitize(string $html): string
    {
        if ($html === '') return '';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        try {
            if (!$doc->loadHTML('<?xml encoding="utf-8"?><div id="appt-custom-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET)) {
                return '';
            }
            $root = $doc->getElementById('appt-custom-root');
            if (!$root) return '';
            foreach (iterator_to_array($root->childNodes) as $node) self::clean($node);
            $result = '';
            foreach ($root->childNodes as $node) $result .= $doc->saveHTML($node);
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private static function clean(\DOMNode $node): void
    {
        if ($node instanceof \DOMComment) { $node->parentNode?->removeChild($node); return; }
        if (!($node instanceof \DOMElement)) return;
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROP, true)) { $node->parentNode?->removeChild($node); return; }
        if (!in_array($tag, self::ALLOWED, true)) {
            $node->parentNode?->replaceChild($node->ownerDocument->createTextNode($node->textContent), $node);
            return;
        }
        if ($tag === 'img') {
            $src = trim($node->getAttribute('src'));
            if (!self::safeImageSource($src)) {
                $node->parentNode?->removeChild($node);
                return;
            }
            // srcset, inline CSS, event handlers and other untrusted
            // attributes must never survive the sanitizer.
            foreach (iterator_to_array($node->attributes) as $attr) {
                $key = strtolower($attr->name);
                if ($key === 'src') {
                    $node->setAttribute('src', $src);
                } elseif ($key === 'alt' || $key === 'title') {
                    if (mb_strlen($attr->value) > 500) {
                        $node->setAttribute($key, mb_substr($attr->value, 0, 500));
                    }
                } elseif ($key === 'width' || $key === 'height') {
                    $value = trim($attr->value);
                    if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 4096) {
                        $node->removeAttributeNode($attr);
                    }
                } elseif ($key === 'loading') {
                    if (!in_array(strtolower($attr->value), ['lazy', 'eager'], true)) {
                        $node->removeAttributeNode($attr);
                    }
                } else {
                    $node->removeAttributeNode($attr);
                }
            }
            // Keep the full booking URL out of external-image Referer headers.
            $node->setAttribute('referrerpolicy', 'no-referrer');
            return;
        }
        foreach (iterator_to_array($node->attributes) as $attr) {
            $key = strtolower($attr->name);
            if ($tag === 'a' && $key === 'href') {
                if (!preg_match('~^(https?://|mailto:|tel:|/(?!/)|#)~i', trim($attr->value))) {
                    $node->removeAttributeNode($attr);
                }
            } elseif (!($tag === 'a' && $key === 'title')) {
                $node->removeAttributeNode($attr);
            }
        }
        foreach (iterator_to_array($node->childNodes) as $child) self::clean($child);
    }
}
