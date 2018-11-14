<?php

namespace atk4\ui;

class Lister extends View
{
    use \atk4\core\HookTrait;

    /**
     * Lister repeats part of it's template. This property will contain
     * the repeating part. Clones from {row}. If your template does not
     * have {row} tag, then entire template will be repeated.
     *
     * @var Template
     */
    public $t_row = null;

    public $defaultTemplate = null;

    public function init()
    {
        parent::init();

        $this->initChunks();
    }

    /**
     * Add Dynamic paginator when scrolling content via Javascript.
     * Will output x item in lister set per ipp until user scroll content to the end of page.
     * When this happen, content will be reload x number of items.
     *
     * @param int    $ipp          Number of item per page
     * @param array  $options      An array with js Scroll plugin options.
     * @param View   $container    The container holding the lister for scrolling purpose. Default to view owner.
     * @param string $scrollRegion A specific template region to render. Render output is append to container html element.
     *
     * @throws Exception
     *
     * @return $this|void
     */
    public function addJsPaginator($ipp, $options = [], $container = null, $scrollRegion = null)
    {
        $scrollable = $this->add(['jsPaginator', 'view' => $container, 'options' => $options]);

        // set initial model limit. can be overwritten by onScroll
        $this->model->setLimit($ipp);

        // add onScroll callback
        $scrollable->onScroll(function ($p) use ($ipp, $scrollRegion) {
            // set/overwrite model limit
            $this->model->setLimit($ipp, ($p - 1) * $ipp);

            // render this View (it will count rendered records !)
            $json = $this->renderJSON(true, $scrollRegion);

            // if there will be no more pages, then replace message=Success to let JS know that there are no more records
            if ($this->_rendered_rows_count < $ipp) {
                $json = json_decode($json, true);
                $json['message'] = 'Done';
                $json = json_encode($json);
            }

            // return json response
            $this->app->terminate($json);
        });

        return $this;
    }

    /**
     * From the current template will extract {row} into $this->t_row.
     *
     * @return void
     */
    public function initChunks()
    {
        if (!$this->template) {
            throw new Exception(['Lister does not have default template. Either supply your own HTML or use "defaultTemplate"=>"lister.html"']);
        }
        if ($this->template->hasTag('row')) {
            $this->t_row = $this->template->cloneRegion('row');
            $this->template->del('rows');
        } else {
            $this->t_row = $this->template;
        }
    }

    /** @var int This will count how many rows are rendered. Needed for jsPaginator for example. */
    protected $_rendered_rows_count = 0;

    public function renderView()
    {
        if (!$this->template) {
            throw new Exception(['Lister requires you to specify template explicitly']);
        }
        $this->t_row->trySet('_id', $this->name);
        $rowHTML = '';

        // if no model is set, don't show anything (even warning)
        if (!$this->model) {
            return parent::renderView();
        }

        $this->_rendered_rows_count = 0;
        foreach ($this->model as $this->current_id => $this->current_row) {
            if ($this->hook('beforeRow') === false) {
                continue;
            }

            $this->t_row->trySet('_title', $this->model->getTitle());
            $this->t_row->trySet('_href', $this->url(['id'=>$this->current_id]));
            $this->t_row->trySet('_id', $this->current_id);

            if ($this->t_row == $this->template) {
                $rowHTML .= $this->t_row->set($this->current_row)->render();
            } else {
                $rowHTML = $this->t_row->set($this->current_row)->render();
                $this->template->appendHTML('rows', $rowHTML);
            }

            $this->_rendered_rows_count++;
        }

        if ($this->t_row == $this->template) {
            $this->template = new Template('{$c}');
            $this->template->setHTML('c', $rowHTML);

            // for some reason this does not work:
            //$this->template->set('_top', $rowHTML);
        }

        return parent::renderView(); //$this->template->render();
    }
}
