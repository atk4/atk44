<?php
// vim:ts=4:sw=4:et:fdm=marker:fdl=0
/**
 * This class is a lightweight template engine. It's based around operating with
 * chunks of HTML code and the main aims are:
 *
 *  - completely remove any logic from templates
 *  - speed up template parsing and manipulation speed
 *
 * @author      Romans <romans@agiletoolkit.org>
 * @copyright   MIT
 *
 * @version     3.0
 *
 * ==[ Version History ]=======================================================
 * 1.0          First public version (released with AModules3 alpha)
 * 1.1          Added support for "_top" tag
 *              Removed support for permanent tags
 *              Much more comments and other fixes
 * 2.0          Reimplemented template parsing, now doing it with regexps
 * 3.0          Re-integrated as part of Agile UI under MIT license
 */

namespace atk4\ui;

class Template implements \ArrayAccess
{
    // {{{ Properties of a template

    /**
     * This array contains list of all tags found inside template implementing
     * faster access when manipulating the template.
     *
     * @var array
     */
    public $tags = array();

    /**
     * This is a parsed contents of the template organized inside an array. This
     * structure makes it very simple to modify any part of the array.
     *
     * @var array
     */
    public $template = array();

    /**
     * Contains information about where the template was loaded from.
     *
     * @var string
     */
    public $template_source = null;

    /** @var string */
    public $default_exception = 'Exception_Template';

    /**
     * Which file template is loaded from.
     */
    public $origin_filename = null;

    public $template_file = null;

    // }}}

    // {{{ Core methods - initialization

    // Template creation, interface functions
    function __construct($template = null) {
        if (!is_null($template)) {
            $this->loadTemplateFromString($template);
        }
    }


    public function __clone()
    {
        parent::__clone();
        $this->template = unserialize(serialize($this->template));

        unset($this->tags);
        $this->rebuildTags();
    }
    /**
     * Returns relevant exception class. Use this method with "throw".
     *
     * @param string $message Static text of exception.
     * @param string $type    Exception class or class postfix
     * @param string $code    Optional error code
     *
     * @return Exception_Template
     */
    public function exception($message = 'Undefined Exception', $type = null, $code = null)
    {
        $o = $this->owner ? $this->owner->__toString() : 'none';

        return parent::exception($message, $type, $code)
            ->addMoreInfo('owner', $o)
            ->addMoreInfo('template', $this->template_source)
            ;
    }

    // }}}

    // {{{ Tag manipulation

    /**
     * Returns true if specified tag is a top-tag of the template.
     *
     * Since Agile Toolkit 4.3 this tag is always called _top
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isTopTag($tag)
    {
        return $tag == '_top';
    }

    /**
     * This is a helper method which populates an array pointing
     * to the place in the template referenced by a said tag.
     *
     * Because there might be multiple tags and getTagRef is
     * returning only one template, it will return the first
     * occurence:
     *
     * {greeting}hello{/},  {greeting}world{/}
     *
     * calling getTagRef('greeting',$template) will point
     * second argument towards &array('hello');
     */
    public function getTagRef($tag, &$template)
    {
        if ($this->isTopTag($tag)) {
            $template = &$this->template;

            return $this;
        }

        @list($tag, $ref) = explode('#', $tag);
        if (!$ref) {
            $ref = 1;
        }
        if (!isset($this->tags[$tag])) {
            throw $this->exception('Tag not found in Template')
                ->addMoreInfo('tag', $tag)
                ->addMoreInfo('tags', implode(', ', array_keys($this->tags)))
                ;
        }
        $template = reset($this->tags[$tag]);

        return $this;
    }

    /**
     * For methods which execute action on several tags, this method
     * will return array of templates. You can then iterate
     * through the array and update all the template values.
     *
     * {greeting}hello{/},  {greeting}world{/}
     *
     * calling getTagRefList('greeting',$template) will point
     * second argument towards array(&array('hello'),&array('world'));
     *
     * If $tag is specified as array, then $templates will
     * contain all occurences of all tags from the array.
     */
    public function getTagRefList($tag, &$template)
    {
        if (is_array($tag)) {
            // TODO: test
            $res = array();
            foreach ($tag as $t) {
                $template = array();
                $this->getTagRefList($t, $te);

                foreach ($template as &$tpl) {
                    $res[] = &$tpl;
                }

                return true;
            }
        }

        if ($this->isTopTag($tag)) {
            $template = &$this->template;

            return false;
        }

        @list($tag, $ref) = explode('#', $tag);
        if (!$ref) {
            if (!isset($this->tags[$tag])) {
                throw $this->exception('Tag not found in Template')
                    ->setTag($tag);
            }
            $template = $this->tags[$tag];

            return true;
        }
        if (!isset($this->tags[$tag][$ref - 1])) {
            throw $this->exception('Tag not found in Template')
                ->setTag($tag);
        }
        $template = array(&$this->tags[$tag][$ref - 1]);

        return true;
    }

    /**
     * Checks if template has defined a specified tag.
     */
    public function hasTag($tag)
    {
        if (is_array($tag)) {
            return true;
        }

        @list($tag, $ref) = explode('#', $tag);

        return isset($this->tags[$tag]) || $this->isTopTag($tag);
    }

    /**
     * Re-create tag indexes from scratch for the whole template
     */
    public function rebuildTags()
    {
        $this->tags = [];

        $this->rebuildTagsRegion($this->template);
    }

    /**
     * Add tags from a specified region
     */
    protected function rebuildTagsRegion(&$template)
    {
        foreach ($template as $tag => &$val) {
            if (is_numeric($tag)) {
                continue;
            }

            @list($key, $ref) = explode('#', $tag);

            $this->tags[$key][$ref] = &$val;
            if (is_array($val)) {
                $this->rebuildTagsRegion($val);
            }
        }
    }
    
    // }}}

    // {{{ Manipulating contents of tags

    /**
     * This function will replace region refered by $tag to a new content.
     *
     * If tag is found inside template several times, all occurences are
     * replaced.
     *
     * ALTERNATIVE USE(2) of this function is to pass associative array as
     * a single argument. This will assign multiple tags with one call.
     * Sample use is:
     *
     *  set($_GET);
     *
     * would read and set multiple region values from $_GET array.
     */
    public function set($tag, $value = null, $encode = true)
    {
        if (!$tag) {
            return $this;
        }
        if (is_array($tag)) {
            if (is_null($value)) {
                foreach ($tag as $s => $v) {
                    $this->trySet($s, $v, $encode);
                }

                return $this;
            }
        }

        if (is_array($value)) {
            return $this;
        }

        if ($encode) {
            $value = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        }

        $this->getTagRefList($tag, $template);
        foreach ($template as $key => &$ref) {
            $ref = [$value];
        }

        return $this;
    }

    /**
     * Set value of a tag to a HTML content. The value is set without
     * encoding, so you must be sure to sanitize.
     */
    public function setHTML($tag, $value = null)
    {
        return $this->set($tag, $value, false);
    }

    /**
     * See setHTML() but won't generate exception for non-existing
     * $tag.
     */
    public function trySetHTML($tag, $value = null)
    {
        return $this->trySet($tag, $value, false);
    }

    /**
     * Same as set(), but won't generate exception for non-existing
     * $tag.
     */
    public function trySet($tag, $value = null, $encode = true)
    {
        if (is_array($tag)) {
            return $this->set($tag, $value, $encode);
        }

        return $this->hasTag($tag) ? $this->set($tag, $value, $encode) : $this;
    }

    /**
     * Add more content inside a tag.
     */
    public function append($tag, $value, $encode = true)
    {
        if ($value instanceof URL) {
            $value = $value->__toString();
        }

        if ($encode) {
            $value = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        }

        $this->getTagRefList($tag, $template);
        foreach ($template as $key => &$ref) {
            $ref[] = $value;
        }

        return $this;
    }

    public function appendHTML($tag, $value)
    {
        return $this->append($tag, $value, false);
    }


    /**
     * Get value of the tag. Note that this may contain an array
     * if tag contains a structure.
     */
    public function get($tag)
    {
        $template = array();
        $this->getTagRef($tag, $template);

        return $template;
    }

    /**
     * Empty contents of specified region. If region contains sub-hierarchy,
     * it will be also removed.
     *
     * IMPORTANT: This does not dispose of the tags which were previously
     * inside the region. This causes some severe pitfalls for the users
     * and ideally must be checked and proper errors must be generated.
     */
    public function del($tag)
    {
        if ($this->isTopTag($tag)) {
            $this->loadTemplateFromString('');

            return $this;
        }

        $this->getTagRefList($tag, $template);
        foreach ($template as &$ref) {
            $ref = [];
        }

        return $this;
    }

    /**
     * Similar to del() but won't throw exception if tag is not present.
     */
    public function tryDel($tag)
    {
        if (is_array($tag)) {
            return $this->del($tag);
        }

        return $this->hasTag($tag) ? $this->del($tag) : $this;
    }

    // }}}

    // {{{ ArrayAccess support
    public function offsetExists($name)
    {
        return $this->hasTag($name);
    }
    public function offsetGet($name)
    {
        return $this->get($name);
    }
    public function offsetSet($name, $val)
    {
        $this->set($name, $val);
    }
    public function offsetUnset($name)
    {
        $this->del($name, null);
    }
    // }}}

    // {{{ Template Manipulations
    
    /**
     * Executes call-back for each matching tag in the template
     */
    public function eachTag($tag, $callable)
    {
        if (!$this->hasTag($tag)) {
            return $this;
        }

        if ($this->getTagRefList($tag, $template)) {
            foreach ($template as $key => $templ) {
                $ref = $tag.'#'.($key + 1);
                $this->tags[$tag][$key] = array(call_user_func($callable, $this->recursiveRender($templ), $ref));
            }
        } else {
            $this->tags[$tag][0] = array(call_user_func($callable, $this->recursiveRender($template), $tag));
        }

        return $this;
    }

    /**
     * Creates a new template using portion of existing template.
     */
    public function cloneRegion($tag)
    {
        if ($this->isTopTag($tag)) {
            return clone $this;
        }

        $n = $this->newInstance();
        $n->template = unserialize(serialize(array('_top#1' => $this->get($tag))));
        $n->rebuildTags();
        $n->source = 'Clone ('.$tag.') of '.$this->source;

        return $n;
    }

    // }}}

    // {{{ Template Loading

    /**
     * Loads template from a specified file
     */
    public function load($template_file)
    {
        $this->template_file = $template_file;
        $this->loadTemplateFromString(file_get_contents($template_file));
        $this->source = 'Loaded from file: '.$template_file;

        return $this;
    }

    /**
     * Initialize current template from the supplied string.
     *
     * @param string $str
     *
     * @return $this
     */
    public function loadTemplateFromString($str)
    {
        $this->template_source = $str;
        $this->source = 'string';
        $this->template = $this->tags = array();
        if (!$str) {
            return;
        }
        $this->tag_cnt = array();

        /* First expand self-closing tags {$tag} -> {tag}{/tag} */
        $str = preg_replace('/{\$([\w]+)}/', '{\1}{/\1}', $str);

        $this->parseTemplate($str);

        return $this;
    }

    // }}}

    // {{{ Template Parsing Engine

    private $tag_cnt = array();
    protected function regTag($tag)
    {
        if (!isset($this->tag_cnt[$tag])) {
            $this->tag_cnt[$tag] = 0;
        }
        return $tag.'#'.(++$this->tag_cnt[$tag]);
    }

    /**
     * Recursively find nested tags inside a string, converting them to array
     */
    protected function parseTemplateRecursive(&$input, &$template)
    {
        while (list(, $tag) = each($input)) {

            // Closing tag
            if ($tag[0] == '/') {
                return substr($tag, 1);
            }

            if ($tag[0] == '$') {
                $tag = substr($tag, 1);
                $full_tag = $this->regTag($tag);
                $template[$full_tag] = '';  // empty value
                $this->tags[$tag][] = &$template[$full_tag];

                // eat next chunk
                $chunk = each($input);
                if ($chunk[1]) {
                    $template[] = $chunk[1];
                }

                continue;
            }

            $full_tag = $this->regTag($tag);

            // Next would be prefix
            list(, $prefix) = each($input);
            $template[$full_tag] = $prefix ? [$prefix] : [];

            $this->tags[$tag][] = &$template[$full_tag];

            $rtag = $this->parseTemplateRecursive($input, $template[$full_tag]);

            $chunk = each($input);
            if ($chunk[1]) {
                $template[] = $chunk[1];
            }
        }
    }

    /**
     * Deploys parse recursion
     */
    protected function parseTemplate($str)
    {
        $tag = '/{([\/$]?[-_\w]*)}/';

        $input = preg_split($tag, $str, -1, PREG_SPLIT_DELIM_CAPTURE);

        list(, $prefix) = each($input);
        $this->template = array($prefix);

        $this->parseTemplateRecursive($input, $this->template);
    }

    // }}}

    // {{{ Template Rendering

    /**
     * Render either a whole template or a specified region. Returns
     * current contents of a template.
     */
    public function render($region = null)
    {
        if ($region) {
            return $this->recursiveRender($this->get($region));
        }

        return $this->recursiveRender($this->template);
    }

    /**
     * Walk through the template array collecting the values
     * and returning them as a string
     */
    protected function recursiveRender(&$template)
    {
        $output = '';
        foreach ($template as $val) {
            if (is_array($val)) {
                $output .= $this->recursiveRender($val);
            } else {
                $output .= $val;
            }
        }

        return $output;
    }
    // }}}

    // {{{ Debugging functions

    /**
     * Returns HTML-formatted code with all tags
     *
    public function _getDumpTags(&$template)
    {
        $s = '';
        foreach ($template as $key => $val) {
            if (is_array($val)) {
                $s .= '<font color="blue">{'.$key.'}</font>'.
                    $this->_getDumpTags($val).'<font color="blue">{/'.$key.'}</font>';
            } else {
                $s .= htmlspecialchars($val);
            }
        }

        return $s;
    }
    /*** TO BE REFACTORED ***/

    /**
     * Output all tags
     *
    public function dumpTags()
    {
        echo '"'.$this->_getDumpTags($this->template).'"';
    }
    /*** TO BE REFACTORED ***/
    // }}}
}
