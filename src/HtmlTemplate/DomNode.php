<?php

declare(strict_types=1);

namespace atk4\ui\HtmlTemplate;

use atk4\ui\Exception;
use atk4\ui\HtmlTemplate;

class DomNode extends HtmlTemplate
{
    /** @const string */
    private const PLACEHOLDER_PREFIX = '__placeholder_7jz2fo4men8df8ax_';

    /**
     * Void elements can not have any content nor end tag.
     *
     * @const string[]
     *
     * @see https://www.w3.org/TR/html52/syntax.html#void-elements
     */
    protected const VOID_TAG_NAMES = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** @var string|HtmlTemplate|null null when template is NOT an element node */
    private $tagName;

    /** @var string|HtmlTemplate[$name] */
    private $attributes = [];

    /** @var self[]|HtmlTemplate[] */
    private $childs = [];

    public function isElement(): bool
    {
        return $this->tagName !== null;
    }

    /**
     * @return string|HtmlTemplate
     */
    public function getTagName()
    {
        if ($this->tagName === null) {
            throw new Exception('Node is not an DOM element');
        }

        return $this->tagName;
    }

    /**
     * @return string[$name]|HtmlTemplate[$name]
     */
    public function getAttributes()
    {
        $this->getTagName(); // assert an element node

        return $this->attributes;
    }

    /**
     * @return self[]|HtmlTemplate[]
     */
    public function getChildNodes()
    {
        return $this->childs;
    }

    /**
     * @return string|HtmlTemplate
     */
    private function parseNativeString(string $str, array $placeholdersMap)
    {
        // optimization for simple strings
//        if (strpos($str, self::PLACEHOLDER_PREFIX) === false) {
//            return $str;
//        }

        $templateStr = preg_replace_callback('~(' . self::PLACEHOLDER_PREFIX . '\d+__)~s', function ($matches) use ($placeholdersMap) {
            $fullTag = $placeholdersMap[$matches[1]];
            $tag = explode('#', $fullTag, 2)[0];

            return '{$' . $tag . '}';
        }, $str);

        $template = new HtmlTemplate($templateStr);
        // TODO link data

        return $template;
    }

    private function parseNativeNodeToTemplateNode(\DOMElement $node, array $placeholdersMap): self
    {
        // TODO once done as nested is hard to debug $template = new static();
        /** @var DomNode $template */
        $template = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $template->tagName = $this->parseNativeString($node->nodeName, $placeholdersMap);
        $templateTree = [];

        /** @var \DOMAttr $attributeNode */
        foreach ($node->attributes as $k => $attributeNode) {
            $l = $this->parseNativeString($attributeNode->nodeValue, $placeholdersMap);
            var_dump($l);
            $template->attributes[$k] = $l;
        }

        // set template tree
        \Closure::bind(function () use ($template, $templateTree) {
            $template->template = $templateTree;
        }, null, HtmlTemplate::class)();
        $template->rebuildTagsIndex();

        return $template;
    }

    protected function parseTemplateTree(array &$inputReversed, string $openedTag = null): array
    {
        $template = parent::parseTemplateTree($inputReversed, $openedTag);
        if ($openedTag !== null) {
            return $template;
        }

        // transform standard template to HTML with custom placeholder tags
        $htmlTemplateParts = [];
        $placeholdersMap = [];
        foreach ($template as $fullTag => $v) {
            if (is_array($v)) {
                $placeholder = self::PLACEHOLDER_PREFIX . count($placeholdersMap) . '__';
                $placeholdersMap[$placeholder] = $fullTag;
                $htmlTemplateParts[] = $placeholder;
            } else {
                $htmlTemplateParts[] = $v;
            }
        }

        // parse HTML
        $doc = new \DOMDocument();
        $mainId = self::PLACEHOLDER_PREFIX . 'main__';
        $html = '<?xml encoding="UTF-8"><html><body><div id="' . $mainId . '">'
            . implode('', $htmlTemplateParts)
            . '</div></body></html>';
        try {
            $doc->loadHTML($html);
        } catch (\Exception $e) {
            throw (new Exception('DOM template parse error'))
                ->addMoreInfo('html', $html);
        }
        $doc->encoding = 'UTF-8';
        $nodes = $doc->getElementById($mainId)->childNodes;
        unset($doc);

        // transform into template tree
        $res = [];
        foreach ($nodes as $node) {
            $res[] = $this->parseNativeNodeToTemplateNode($node, $placeholdersMap);
        }

        if ($openedTag !== null) {
            return $res;
        }

//        if (isset($_GET['dump'])) {
//            var_dump($doc->saveHTML());
        $mainNode = $nodes->item(0);
        var_dump($mainNode->ownerDocument->saveHTML($mainNode));
//        }

        print_r($res);
        var_dump($res[0]->render());

        //var_dump(

        exit;
    }

    /**
     * @param string|HtmlTemplate $template
     */
    private function renderStringOrTemplate($template): string
    {
        return $template instanceof HtmlTemplate ? $template->render() : $template;
    }

    public function renderTagName(): string
    {
        return strtolower($this->renderStringOrTemplate($this->getTagName()));
    }

    public function renderAttribute(string $name): string
    {
        return $this->renderStringOrTemplate($this->getAttributes()[$name]);
    }

    public function render(string $region = null): string
    {
        if ($region !== null) {
            throw new Exception('Render of region is not supported');
        }

        $res = '';
        if ($this->tagName !== null) {
            $tagNameStr = $this->renderTagName();
            if (in_array($tagNameStr, static::VOID_TAG_NAMES, true)) {
                if (count($this->attributes) > 0 || count($this->childs) > 0) {
                    throw new Exception('Void element can not have attributes nor childs');
                }

                return '<' . $tagNameStr . ' />';
            }

            // TODO fix escaping here or render via Template only?
            $attributesStr = implode(' ', array_map(function ($k, $v) {
                return $k . '="' . $this->renderStringOrTemplate($v) . '"';
            }, array_keys($this->attributes), $this->attributes));

            $res .= '<' . $tagNameStr . ($attributesStr !== '' ? ' ' . $attributesStr : '') . '>';
        }

        $res .= implode('', array_map(function (HtmlTemplate $template) {
            return $template->render();
        }, $this->childs));

        if ($this->tagName !== null) {
            $res .= '</' . $tagNameStr . '>';
        }

        return $res;
    }
}
