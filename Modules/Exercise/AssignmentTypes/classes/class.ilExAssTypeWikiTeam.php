<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Team wiki type
 *
 * @author Alex Killing <killing@leifos.de>
 */
class ilExAssTypeWikiTeam implements ilExAssignmentTypeInterface
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
        return true;
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
        $lng->loadLanguageModule("wiki");
        return $lng->txt("wiki_type_wiki_team");
    }

    /**
     * @inheritdoc
     */
    public function getSubmissionType()
    {
        return ilExSubmission::TYPE_REPO_OBJECT;
    }

    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam()
    {
        return true;
    }

    /**
     * Submit wiki
     *
     * @param int $a_ass_id
     * @param int $a_user_id
     * @param int $a_wiki_ref_id
     * @return bool|void
     * @throws ilTemplateException
     * @throws ilWikiExportException
     */
    public function submitWiki(int $a_ass_id, int $a_user_id, int $a_wiki_ref_id)
    {
        $ass = new ilExAssignment($a_ass_id);
        $submission = new ilExSubmission($ass, $a_user_id);

        if (!$submission->canSubmit()) {
            return;
        }

        $wiki = new ilObjWiki((int) $a_wiki_ref_id);
        $exp = new \ILIAS\Wiki\Export\WikiHtmlExport($wiki);
        //$exp->setMode(ilWikiHTMLExport::MODE_USER);
        $file = $exp->buildExportFile();

        $size = filesize($file);
        if ($size) {
            $submission->deleteAllFiles();

            $meta = array(
                "name" => $a_wiki_ref_id,
                "tmp_name" => $file,
                "size" => $size
            );
            $submission->uploadFile($meta, true);

            $this->handleNewUpload($ass, $submission);
            return true;
        }
        return false;
    }

    // @todo move to trait
    protected function handleNewUpload(
        ilExAssignment $ass,
        ilExSubmission $submission,
        $a_no_notifications = false
    ) {
        $has_submitted = $submission->hasSubmitted();

        // we need one ref id here
        $exc_ref_ids = ilObject::_getAllReferences($ass->getExerciseId());
        $exc_ref_id = current($exc_ref_ids);

        $exc = new ilObjExercise($ass->getExerciseId(), false);

        $exc->processExerciseStatus(
            $ass,
            $submission->getUserIds(),
            $has_submitted,
            $submission->validatePeerReviews()
        );

        if ($has_submitted &&
            !$a_no_notifications) {
            $users = ilNotification::getNotificationsForObject(
                ilNotification::TYPE_EXERCISE_SUBMISSION,
                $exc->getId()
            );

            $not = new ilExerciseMailNotification();
            $not->setType(ilExerciseMailNotification::TYPE_SUBMISSION_UPLOAD);
            $not->setAssignmentId($ass->getId());
            $not->setRefId($exc_ref_id);
            $not->setRecipients($users);
            $not->send();
        }
    }

    /**
     * @inheritdoc
     */
    public function cloneSpecificProperties(ilExAssignment $source, ilExAssignment $target)
    {
        $source_ar = new ilExAssWikiTeamAR($source->getId());
        $target_ar = new ilExAssWikiTeamAR();
        $target_ar->setId($target->getId());
        $target_ar->setTemplateRefId($source_ar->getTemplateRefId());
        $target_ar->setContainerRefId($source_ar->getContainerRefId());
        $target_ar->save();
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
