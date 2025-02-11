<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * File upload type
 *
 * @author Alex Killing <killing@leifos.de>
 */
class ilExAssTypeUpload implements ilExAssignmentTypeInterface
{
    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * Constructor
     *
     * @param ilLanguage|null $a_lng
     */
    public function __construct(ilLanguage $a_lng = null)
    {
        global $DIC;

        $this->lng = ($a_lng)
            ? $a_lng
            : $DIC->language();
    }

    /**
     * @inheritdoc
     */
    public function isActive()
    {
        return true;
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
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        $lng = $this->lng;

        return $lng->txt("exc_type_upload");
    }

    /**
     * @inheritdoc
     */
    public function getSubmissionType()
    {
        return ilExSubmission::TYPE_FILE;
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
        return false;
    }

    /**
     *  @inheritdoc
     */
    public function getStringIdentifier() : string
    {
        // TODO: Implement getSubmissionStringIdentifier() method.
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
