<?php
namespace OCA\Appointments\Backend;

final class HtmlTemplate
{
    private const ALLOWED = ['a','b','blockquote','br','code','div','em','h1','h2','h3','h4','hr','i','li','ol','p','pre','span','strong','u','ul'];
    private const DROP = ['script','style','iframe','object','embed','form','input','button','svg','math','img','link','meta','base','video','audio','source','textarea','select'];

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
