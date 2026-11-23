<?php

namespace FluentSupport\App\Modules\MCP\Helpers;

/**
 * Small DOM-based HTML to Markdown converter for MCP output.
 *
 * Agents read ticket threads and integration widgets as text. Flattening HTML
 * to one line destroys pasted logs, lists and tables, so this keeps the
 * structure as Markdown. It is a reader, not a round-trip converter: nothing
 * is escaped and unknown tags just render their children.
 *
 * Bundled instead of a Composer package because vendor libraries are not
 * namespace-scoped and would clash with other plugins shipping the same one.
 */
class HtmlToMarkdown
{
    // Placeholders survive the whitespace cleanup and are swapped back at the end.
    const INDENT = "\x01";
    const PRE    = "\x02";
    // A line break that merges with its neighbours, so stacked <div>s read as lines, not paragraphs.
    const SOFT   = "\x03";

    private static $skipTags = ['script', 'style', 'head', 'title', 'meta', 'link', 'noscript', 'template'];

    private static $blockTags = [
        'p', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav',
        'address', 'figure', 'figcaption', 'dl', 'dt', 'dd', 'center', 'form', 'fieldset',
    ];

    /** @var string[] */
    private $preBlocks = [];

    public static function convert($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // Plain text: keep its own line breaks.
        if (strpos($html, '<') === false) {
            $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return self::tidy(str_replace(["\r\n", "\r"], "\n", $text));
        }

        // ext/dom is enabled by default but can be compiled out; degrade to text.
        if (!class_exists('\DOMDocument')) {
            return self::tidy(wp_strip_all_tags($html));
        }

        $dom = new \DOMDocument();
        $libXmlState = libxml_use_internal_errors(true);
        $loaded      = $dom->loadHTML(
            '<?xml encoding="UTF-8"?>' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($libXmlState);

        // Walk the document element rather than a wrapper <div> of our own: an
        // unbalanced </div> in stored email HTML would close the wrapper early and
        // everything after it would be silently dropped. <head> is skipped below and
        // <html>/<body> just render their children, so this covers content libxml
        // places after a stray </body> too.
        $root = $loaded ? $dom->documentElement : null;
        if (!$root) {
            return self::tidy(wp_strip_all_tags($html));
        }

        $converter = new self();
        $markdown  = $converter->children($root);

        return $converter->restorePre(self::tidy($markdown));
    }

    private static function tidy($text)
    {
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = preg_replace_callback('/ *(?:[\n' . self::SOFT . '] *)+/', function ($match) {
            return substr_count($match[0], "\n") >= 2 ? "\n\n" : "\n";
        }, $text);
        $text = str_replace(self::INDENT, ' ', $text);

        return trim($text);
    }

    private function restorePre($text)
    {
        return preg_replace_callback('/' . self::PRE . '(\d+)' . self::PRE . '/', function ($match) {
            return $this->preBlocks[(int) $match[1]] ?? '';
        }, $text);
    }

    private function children(\DOMNode $node)
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    private function node(\DOMNode $node)
    {
        if ($node instanceof \DOMText) {
            $text = str_replace([self::INDENT, self::PRE, self::SOFT], '', $node->nodeValue);
            return preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::$skipTags, true)) {
            return '';
        }

        switch ($tag) {
            case 'br':
                return "\n";
            case 'hr':
                return "\n\n---\n\n";
            case 'div':
                $content = trim($this->children($node));
                if ($content === '') {
                    // Gmail writes a blank line as <div><br></div>.
                    return $node->getElementsByTagName('br')->length ? "\n\n" : '';
                }
                return self::SOFT . $content . self::SOFT;
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $text = $this->inline($node);
                return $text === '' ? '' : "\n\n" . str_repeat('#', (int) $tag[1]) . ' ' . $text . "\n\n";
            case 'strong':
            case 'b':
                return $this->wrap($node, '**');
            case 'em':
            case 'i':
                return $this->wrap($node, '_');
            case 's':
            case 'del':
            case 'strike':
                return $this->wrap($node, '~~');
            case 'code':
                return $this->wrap($node, '`');
            case 'pre':
                return $this->pre($node);
            case 'a':
                return $this->link($node);
            case 'img':
                return $this->image($node);
            case 'ul':
            case 'ol':
                return $this->listBlock($node, $tag === 'ol');
            case 'li':
                return "\n- " . $this->itemContent($node) . "\n";
            case 'blockquote':
                return $this->blockquote($node);
            case 'table':
                return $this->table($node);
        }

        if (in_array($tag, self::$blockTags, true)) {
            return "\n\n" . trim($this->children($node)) . "\n\n";
        }

        return $this->children($node);
    }

    // Inline contexts (headings, links, table cells) have no line breaks: a soft
    // break from a nested <div> folds into a space like a newline does.
    private function inline(\DOMNode $node)
    {
        return trim(preg_replace('/[\s' . self::SOFT . ']*[\n' . self::SOFT . '][\s' . self::SOFT . ']*/', ' ', $this->children($node)));
    }

    // List items keep line breaks but a soft break is a plain newline there.
    private function itemContent(\DOMNode $node)
    {
        $content = str_replace(self::SOFT, "\n", $this->children($node));

        return trim(preg_replace('/\n{2,}/', "\n", $content));
    }

    // Keep the surrounding spaces outside the markers: "a **b** c", not "a** b **c".
    private function wrap(\DOMNode $node, $marker)
    {
        $raw  = $this->children($node);
        $text = trim($raw);
        if ($text === '') {
            return $raw;
        }

        $lead  = preg_match('/^\s/u', $raw) ? ' ' : '';
        $trail = preg_match('/\s$/u', $raw) ? ' ' : '';

        return $lead . $marker . $text . $marker . $trail;
    }

    private function pre(\DOMElement $node)
    {
        // Some editors put <br> inside <pre>; textContent would drop them.
        foreach (iterator_to_array($node->getElementsByTagName('br')) as $br) {
            $br->parentNode->replaceChild($node->ownerDocument->createTextNode("\n"), $br);
        }

        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $node->textContent);
        $text = trim($text, "\n");
        if (trim($text) === '') {
            return '';
        }

        $this->preBlocks[] = "```\n" . $text . "\n```";

        return "\n\n" . self::PRE . (count($this->preBlocks) - 1) . self::PRE . "\n\n";
    }

    private function link(\DOMElement $node)
    {
        $text = $this->inline($node);
        $href = trim($node->getAttribute('href'));

        if ($href === '' || $href[0] === '#' || stripos($href, 'javascript:') === 0) {
            return $text;
        }

        $bareHref = preg_replace('#^(mailto:|tel:)#i', '', $href);
        if ($text === '' || $text === $href || $text === $bareHref) {
            return $bareHref;
        }

        return '[' . $text . '](' . $href . ')';
    }

    private function image(\DOMElement $node)
    {
        // Tracking pixels.
        if (in_array($node->getAttribute('width'), ['0', '1'], true) || in_array($node->getAttribute('height'), ['0', '1'], true)) {
            return '';
        }

        $alt = trim(preg_replace('/\s+/', ' ', $node->getAttribute('alt')));
        $src = trim($node->getAttribute('src'));

        if ($src === '' || stripos($src, 'data:') === 0) {
            return $alt !== '' ? '[image: ' . $alt . ']' : '[image]';
        }

        return '![' . $alt . '](' . $src . ')';
    }

    private function listBlock(\DOMElement $node, $ordered)
    {
        $lines  = [];
        $number = $ordered && is_numeric($node->getAttribute('start')) ? (int) $node->getAttribute('start') : 1;

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->tagName) !== 'li') {
                // Stray text or a nested list without an <li> wrapper.
                $stray = trim($this->node($child));
                if ($stray !== '') {
                    $lines[] = $stray;
                }
                continue;
            }

            $prefix  = $ordered ? ($number++) . '. ' : '- ';
            $content = $this->itemContent($child);
            $indent  = str_repeat(self::INDENT, strlen($prefix));

            $lines[] = $prefix . str_replace("\n", "\n" . $indent, $content);
        }

        return $lines ? "\n\n" . implode("\n", $lines) . "\n\n" : '';
    }

    private function blockquote(\DOMElement $node)
    {
        $content = trim(self::tidy($this->children($node)));
        if ($content === '') {
            return '';
        }

        return "\n\n> " . str_replace("\n", "\n> ", $content) . "\n\n";
    }

    private function table(\DOMElement $node)
    {
        $rows = [];
        foreach ($this->tableRows($node) as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof \DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    $cells[] = $cell;
                }
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }

        if (!$rows) {
            return $this->children($node);
        }

        $columns = max(array_map('count', $rows));

        // Email layout tables (nested tables, single column) read better as plain blocks.
        if ($columns < 2 || $node->getElementsByTagName('table')->length > 0) {
            $out = '';
            foreach ($rows as $cells) {
                foreach ($cells as $cell) {
                    $out .= self::SOFT . trim($this->children($cell)) . self::SOFT;
                }
            }
            return "\n" . $out . "\n";
        }

        $lines = [];
        foreach ($rows as $index => $cells) {
            $values = [];
            foreach ($cells as $cell) {
                $values[] = str_replace('|', '\|', $this->inline($cell));
            }
            $values  = array_pad($values, $columns, '');
            $lines[] = '| ' . implode(' | ', $values) . ' |';

            if ($index === 0) {
                $lines[] = '|' . str_repeat(' --- |', $columns);
            }
        }

        return "\n\n" . implode("\n", $lines) . "\n\n";
    }

    /**
     * Rows of this table only, not of tables nested inside its cells.
     *
     * @return \DOMElement[]
     */
    private function tableRows(\DOMElement $table)
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'tr') {
                $rows[] = $child;
            } elseif (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($child->childNodes as $tr) {
                    if ($tr instanceof \DOMElement && strtolower($tr->tagName) === 'tr') {
                        $rows[] = $tr;
                    }
                }
            }
        }

        return $rows;
    }
}
