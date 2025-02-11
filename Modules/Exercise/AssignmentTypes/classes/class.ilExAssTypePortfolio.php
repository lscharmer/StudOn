<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Portfolio type
 *
 * @author Alex Killing <killing@leifos.de>
 */
class ilExAssTypePortfolio implements ilExAssignmentTypeInterface
{
    /**
     * @var ilSetting
     */
    protected $setting;

    /**
     * @var ilLanguage
     */
    protected $lng;

    protected $identifier_str;

    /**
     * Constructor
     *
     * @param ilSetting|null $a_setting
     * @param ilLanguage|null $a_lng
     */
    public function __construct(ilSetting $a_setting = null, ilLanguage $a_lng = null)
    {
        global $DIC;

        $this->setting = ($a_setting)
            ? $a_setting
            : $DIC["ilSetting"];

        $this->lng = ($a_lng)
            ? $a_lng
            : $DIC->language();
    }

    /**
     * @inheritdoc
     */
    public function isActive()
    {
        if ($this->setting->get('user_portfolios')) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function usesTeams()
    {
        return false;
    }

    // fau: exAssHook - new function hasFiles()
    public function hasFiles()
    {
        return true;
    }
    // fau.

    /**
     * @inheritdoc
     */
    public function usesFileUpload()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        $lng = $this->lng;

        return $lng->txt("exc_type_portfolio");
    }

    /**
     * @inheritdoc
     */
    public function getSubmissionType()
    {
        return ilExSubmission::TYPE_OBJECT;
    }

    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function cloneSpecificProperties(ilExAssignment $source, ilExAssignment $target)
    {
    }

    /**
     * @inheritdoc
     */
    public function supportsWebDirAccess() : bool
    {
        return true;
    }

    /**
     *  @inheritdoc
     */
    public function getStringIdentifier() : string
    {
        return ilExAssignmentTypes::STR_IDENTIFIER_PORTFOLIO;
    }

    // fau: exAssHook - new function isManualGradingSupported()
    /**
     * @inheritdoc
     */
    public function isManualGradingSupported($a_ass) : bool
    {
        return true;
    }
    //fau.

}
