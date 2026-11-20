<?php

namespace App\Support;

use Illuminate\Support\Str;

class ArticleMarkup
{
    public static function render(string $body): string
    {
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="article-body">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach (['img', 'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'video', 'audio'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        foreach ($dom->getElementsByTagName('a') as $a) {
            $a->setAttribute('rel', 'nofollow ugc noopener noreferrer');
        }
        $root = $dom->getElementById('article-body');
        if (! $root) {
            return '';
        }
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }
}
