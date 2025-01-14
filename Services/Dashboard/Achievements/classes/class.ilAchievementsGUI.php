<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Achivements GUI
 *
 * @ilCtrl_Calls ilAchievementsGUI: ilLearningProgressGUI, ilPersonalSkillsGUI, ilBadgeProfileGUI, ilLearningHistoryGUI
 *
 * @author killing@leifos.de
 * @ingroup ServicesPersonalDesktop
 */
class ilAchievementsGUI
{
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * @var ilAchievements
     */
    protected $achievements;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilTabsGUI
     */
    protected $tabs;

    /**
     * @var ilGlobalTemplate
     */
    private $main_tpl;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->achievements = new ilAchievements();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->main_tpl = $DIC->ui()->mainTemplate();
    }

    /**
     * Execute command
     * @throws ilCtrlException
     */
    public function executeCommand()
    {
        $ctrl = $this->ctrl;
        $main_tpl = $this->main_tpl;
        $lng = $this->lng;

        $lng->loadLanguageModule("lhist");

        $next_class = $ctrl->getNextClass($this);
        $cmd = $ctrl->getCmd("show");


        switch ($next_class) {
            case "illearningprogressgui":
                $main_tpl->setTitle($lng->txt("learning_progress"));
                $main_tpl->setTitleIcon(ilUtil::getImagePath("icon_trac.svg"));
                include_once './Services/Tracking/classes/class.ilLearningProgressGUI.php';
                $new_gui = new ilLearningProgressGUI(ilLearningProgressGUI::LP_CONTEXT_PERSONAL_DESKTOP, 0);
                $ctrl->forwardCommand($new_gui);
                break;

            case 'illearninghistorygui':
                $main_tpl->setTitle($lng->txt("lhist_learning_history"));
                $main_tpl->setTitleIcon(ilUtil::getImagePath("icon_lhist.svg"));
                $lhistgui = new ilLearningHistoryGUI();
                $ctrl->forwardCommand($lhistgui);
                $this->main_tpl->printToStdout();
                break;

            case 'ilpersonalskillsgui':
                $main_tpl->setTitle($lng->txt("skills"));
                $main_tpl->setTitleIcon(ilUtil::getImagePath("icon_skmg.svg"));
                $skgui = new ilPersonalSkillsGUI();
                $ctrl->forwardCommand($skgui);
                $this->main_tpl->printToStdout();
                break;

            case 'ilbadgeprofilegui':
                $main_tpl->setTitle($lng->txt("obj_bdga"));
                $main_tpl->setTitleIcon(ilUtil::getImagePath("icon_bdga.svg"));
                include_once './Services/Badge/classes/class.ilBadgeProfileGUI.php';
                $bgui = new ilBadgeProfileGUI();
                $ctrl->forwardCommand($bgui);
                $this->main_tpl->printToStdout();
                break;

            case 'ilusercertificategui':
                $main_tpl->setTitle($lng->txt("obj_cert"));
                $main_tpl->setTitleIcon(ilUtil::getImagePath("icon_cert.svg"));
                $cgui = new ilUserCertificateGUI();
                $ctrl->forwardCommand($cgui);
                $this->main_tpl->printToStdout();
                break;

            default:
                if (in_array($cmd, array("show"))) {
                    $this->$cmd();
                }
                $this->main_tpl->printToStdout();
                break;
        }
    }

    /**
     * Show (redirects to first active service)
     */
    protected function show()
    {
        $ctrl = $this->ctrl;

        $gui_classes = $this->getGUIClasses();
        $first_service = current($this->achievements->getActiveServices());
        if ($first_service) {
            $ctrl->redirectByClass(["ildashboardgui", "ilachievementsgui", $gui_classes[$first_service]]);
        }
    }

    /**
     * Set tabs
     */
    protected function setTabs($activate)
    {
        $tabs = $this->tabs;
        $links = $this->getLinks();

        foreach ($this->achievements->getActiveServices() as $s) {
            $tabs->addTab("achieve_" . $s, $links[$s]["txt"], $links[$s]["link"]);
        }
        $tabs->activateTab("achieve_" . $activate);
    }

    /**
     * Get link
     *
     * @param
     * @return
     */
    protected function getLinks()
    {
        $ctrl = $this->ctrl;
        $lng = $this->lng;

        $lng->loadLanguageModule("lhist");
        $gui_classes = $this->getGUIClasses();

        $links = [
            ilAchievements::SERV_LEARNING_HISTORY => [
                "txt" => $lng->txt("lhist_learning_history")
            ],
            ilAchievements::SERV_COMPETENCES => [
                "txt" => $lng->txt("skills")
            ],
// fau: hideLpOnDesktop - don't show learning progress tab in achievements
// see https://mantis.ilias.de/view.php?id=23396
//			ilAchievements::SERV_LEARNING_PROGRESS => [
//				"txt" => $lng->txt("learning_progress")
//			],
// fau.
            ilAchievements::SERV_BADGES => [
                "txt" => $lng->txt('obj_bdga')
            ],
            ilAchievements::SERV_CERTIFICATES => [
                "txt" => $lng->txt("obj_cert")
            ]
        ];

        foreach ($links as $k => $v) {
            $links[$k]["link"] = $ctrl->getLinkTargetByClass(["ildashboardgui", "ilachievementsgui", $gui_classes[$k]]);
        }

        return $links;
    }

    /**
     * Get GUI class
     *
     * @param
     * @return
     */
    protected function getGUIClasses()
    {
        $gui_classes = [
            ilAchievements::SERV_LEARNING_HISTORY => "ilLearningHistoryGUI",
            ilAchievements::SERV_COMPETENCES => "ilpersonalskillsgui",
            ilAchievements::SERV_LEARNING_PROGRESS => "illearningprogressgui",
            ilAchievements::SERV_BADGES => "ilbadgeprofilegui",
            ilAchievements::SERV_CERTIFICATES => "ilusercertificategui"
        ];

        return $gui_classes;
    }
}
