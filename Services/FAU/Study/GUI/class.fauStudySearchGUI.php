<?php

use FAU\BaseGUI;
use FAU\Study\Data\SearchCondition;
use FAU\Study\Search;
use ILIAS\UI\Component\Item\Group;

/**
 * Search for events from campo
 *
 * @ilCtrl_Calls fauStudySearchGUI: ilPropertyFormGUI
 */
class fauStudySearchGUI extends BaseGUI
{
    protected Search $search;

    public function __construct() {
        parent::__construct();
        $this->search = $this->dic->fau()->study()->search();
        $this->lng->loadLanguageModule('search');
    }

    /**
     * Execute a command
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd('show');
        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilPropertyFormGUI::class):
                $form = $this->getSearchForm($this->search->getCondition());
                $this->ctrl->forwardCommand($form);
                break;

            default:
                switch ($cmd)
                {
                    case "show":
                    case 'search':
                        $this->$cmd();
                        break;

                    default:
                        $this->tpl->setContent('unknown command: ' . $cmd);
                }
        }
        $this->tpl->setTitle($this->lng->txt('fau_search'));
        $this->tpl->setTitleIcon(ilObject::_getIcon("", "big", "src"));
        $this->tpl->printToStdout();
    }

    protected function show()
    {
        $cond = $this->search->getCondition();
        $form = $this->getSearchForm($cond);
        $listHtml = '';
        if (!$cond->isEmpty()) {
            $listHtml = $this->dic->ui()->renderer()->render($this->getEventList($cond));
        }
        $this->tpl->setContent($form->getHTML() . $listHtml);
    }

    protected function search()
    {
        $form = $this->getSearchForm($this->search->getCondition());
        $form->checkInput();
        $form->setValuesByPost();
        $this->search->setCondition($this->getFormCondition($form));
        $this->ctrl->redirect($this, 'show');

    }

    protected function getSearchForm(SearchCondition $condition): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));

        $pattern = new ilTextInputGUI($this->lng->txt('fau_search_title'), 'pattern');
        $pattern->setInfo($this->lng->txt('fau_search_title_info'));
        $pattern->setValue($condition->getPattern());
        $form->addItem($pattern);

        $term = new ilSelectInputGUI($this->lng->txt('studydata_semester'), 'term_id');
        $term->setOptions($this->dic->fau()->study()->getTermSearchOptions($condition->getTermId(), false));
        $term->setValue($condition->getIliasRefId());
        $form->addItem($term);

        $ref = new fauRepositorySelectorInputGUI($this->lng->txt('search_area'), 'search_ref_id');
        $ref->setTypeWhitelist(['root', 'cat']);
        $ref->setSelectableTypes(['cat']);
        $ref->setValue($condition->getIliasRefId());
        $form->addItem($ref);

        $form->addCommandButton('search', $this->lng->txt('search'));
        return $form;
    }

    protected function getFormCondition(ilPropertyFormGUI $form) : SearchCondition
    {
        /** @var ilSelectInputGUI $term_id */
        $term_id = $form->getItemByPostVar('term_id');
        /** @var fauRepositorySelectorInputGUI $ref_id */
        $ref_id = $form->getItemByPostVar('search_ref_id');


        return new SearchCondition(
            '',
            (string) $term_id->getValue(),
            0,
            0,
            (int) $ref_id->getValue(),
            false
        );
    }


    protected function getEventList(SearchCondition $condition) : Group
    {


        $items = [];

        $items[] = $this->factory->item()->standard('title')
             ->withLeadIcon($this->factory->symbol()->icon()->standard('crs', 'course', 'medium'))
             ->withProperties([
                    'key' => 'value',
             ]);

        return $this->factory->item()->group('events', $items);
    }

}