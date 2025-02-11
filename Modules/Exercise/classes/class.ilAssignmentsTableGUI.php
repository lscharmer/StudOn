<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Assignments table
*
* @author Alex Killing <alex.killing@gmx.de>
*
* @ingroup ModulesExercise
*/
class ilAssignmentsTableGUI extends ilTable2GUI
{
    /**
     * @var ilExAssignmentTypes
     */
    protected $types;

    /**
     * @var ilExcRandomAssignmentManager
     */
    protected $random_manager;

    /**
    * Constructor
    */
    public function __construct($a_parent_obj, $a_parent_cmd, $a_exc_id)
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();
        $this->types = ilExAssignmentTypes::getInstance();

        $this->exc_id = $a_exc_id;
        $this->setId("excass" . $this->exc_id);

        $request = $DIC->exercise()->internal()->request();
        $this->random_manager = $DIC->exercise()->internal()->service()->getRandomAssignmentManager(
            $request->getRequestedExercise()
        );
        
        parent::__construct($a_parent_obj, $a_parent_cmd);
    
        $this->setTitle($lng->txt("exc_assignments"));
        $this->setTopCommands(true);
        
        // if you add pagination and disable the unlimited setting:
        // fix saving of ordering of single pages!
        $this->setLimit(9999);
        
        $this->addColumn("", "", "1", true);
        $this->addColumn($this->lng->txt("title"), "title");
        $this->addColumn($this->lng->txt("exc_assignment_type"), "type");
        $this->addColumn($this->lng->txt("exc_presentation_order"), "order_val");
        $this->addColumn($this->lng->txt("exc_start_time"), "start_time");
        $this->addColumn($this->lng->txt("exc_deadline"), "deadline");
        // fau: exGradeTime - add column for grade time
        $this->addColumn($this->lng->txt("exc_grade_start"), "grade_start");
        // fau.
        // fau: exResTime - add column for result time
        $this->addColumn($this->lng->txt("exc_result_time"), "result_time");
        // fau.
        $this->addColumn($this->lng->txt("exc_mandatory"), "mandatory");
        $this->addColumn($this->lng->txt("exc_peer_review"), "peer");
        $this->addColumn($this->lng->txt("exc_instruction"), "", "30%");
        $this->addColumn($this->lng->txt("actions"));
        
        $this->setDefaultOrderField("val_order");
        $this->setDefaultOrderDirection("asc");
        
        //$this->setDefaultOrderField("name");
        //$this->setDefaultOrderDirection("asc");
        
        $this->setEnableHeader(true);
        $this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
        $this->setRowTemplate("tpl.exc_assignments_row.html", "Modules/Exercise");
        //$this->disable("footer");
        $this->setEnableTitle(true);
        $this->setSelectAllCheckbox("id");

        $this->addMultiCommand("confirmAssignmentsDeletion", $lng->txt("delete"));
        
        $this->addCommandButton("orderAssignmentsByDeadline", $lng->txt("exc_order_by_deadline"));
        $this->addCommandButton("saveAssignmentOrder", $lng->txt("exc_save_order"));
        //$this->addCommandButton("addAssignment", $lng->txt("exc_add_assignment"));
        
        $data = ilExAssignment::getAssignmentDataOfExercise($this->exc_id);
        foreach ($data as $idx => $row) {
            // #14450
            if ($row["peer"]) {
                $data[$idx]["peer_invalid"] = true;
                $peer_review = new ilExPeerReview(new ilExAssignment($row["id"]));
                $peer_reviews = $peer_review->validatePeerReviewGroups();
                $data[$idx]["peer_invalid"] = $peer_reviews["invalid"];
            }
            $data[$idx]["ass_type"] = $this->types->getById($row["type"]);
            $data[$idx]["type"] = $data[$idx]["ass_type"]->getTitle();
        }
        
        $this->setData($data);
    }
    
    public function numericOrdering($a_field)
    {
        // #12000
        if (in_array($a_field, array("order_val", "deadline", "start_time"))) {
            return true;
        }
        return false;
    }
    
    /**
    * Fill table row
    */
    protected function fillRow($d)
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $this->tpl->setVariable("ID", $d["id"]);

        $ass = new ilExAssignment($d["id"]);

        if ($ass->getDeadlineMode() == ilExAssignment::DEADLINE_ABSOLUTE) {
            if ($d["deadline"] > 0) {
                $dl = ilDatePresentation::formatDate(new ilDateTime($d["deadline"], IL_CAL_UNIX));
                if ($d["deadline2"] > 0) {
                    $dl .= "<br />(" . ilDatePresentation::formatDate(new ilDateTime(
                        $d["deadline2"],
                        IL_CAL_UNIX
                    )) . ")";
                }
                $this->tpl->setVariable("TXT_DEADLINE", $dl);
            } else {
                $this->tpl->setVariable("TXT_DEADLINE", "-");
            }
        } else {
            if ($ass->getRelativeDeadline() > 0) {
                $dl = "" . $ass->getRelativeDeadline() . " " . $this->lng->txt("days");
            }
            if ($ass->getRelDeadlineLastSubmission() > 0) {
                if ($dl != "") {
                    $dl .= " / ";
                }
                $dl .= ilDatePresentation::formatDate(new ilDateTime($ass->getRelDeadlineLastSubmission(), IL_CAL_UNIX));
            }
            $this->tpl->setVariable("TXT_DEADLINE", $dl);
        }
        if ($d["start_time"] > 0) {
            $this->tpl->setVariable(
                "TXT_START_TIME",
                ilDatePresentation::formatDate(new ilDateTime($d["start_time"], IL_CAL_UNIX))
            );
        }
        // fau: exGradeTime - fill column for grade time
        if ($d["grade_start"] > 0) {
            $this->tpl->setVariable(
                "TXT_GRADE_TIME",
                ilDatePresentation::formatDate(new ilDateTime($d["grade_start"], IL_CAL_UNIX))
            );
        }
        // fau.
        // fau: exResTime - fill column for result time
        if ($d["res_time"] > 0) {
            $this->tpl->setVariable(
                "TXT_RESULT_TIME",
                ilDatePresentation::formatDate(new ilDateTime($d["res_time"], IL_CAL_UNIX))
            );
        }
        // fau.
        $this->tpl->setVariable(
            "TXT_INSTRUCTIONS",
            nl2br(trim(ilUtil::shortenText(strip_tags($d["instruction"]), 200, true)))
        );

        if (!$this->random_manager->isActivated()) {
            if ($d["mandatory"]) {
                $this->tpl->setVariable("TXT_MANDATORY", $lng->txt("yes"));
            } else {
                $this->tpl->setVariable("TXT_MANDATORY", $lng->txt("no"));
            }
        } else {
            $this->tpl->setVariable("TXT_MANDATORY", $lng->txt("exc_random"));
        }
        
        $ilCtrl->setParameter($this->parent_obj, "ass_id", $d["id"]);
        
        if ($d["peer"]) {
            $this->tpl->setVariable("TXT_PEER", $lng->txt("yes") . " (" . $d["peer_min"] . ")");
            
            if ($d["peer_invalid"]) {
                $this->tpl->setVariable("TXT_PEER_INVALID", $lng->txt("exc_peer_reviews_invalid_warning"));
            }

            if ($ass->afterDeadlineStrict()) {	// see #22246
                $this->tpl->setVariable("TXT_PEER_OVERVIEW", $lng->txt("exc_peer_review_overview"));
                $this->tpl->setVariable(
                    "CMD_PEER_OVERVIEW",
                    $ilCtrl->getLinkTargetByClass("ilexpeerreviewgui", "showPeerReviewOverview")
                );
            }
        } else {
            $this->tpl->setVariable("TXT_PEER", $lng->txt("no"));
        }
        
        $this->tpl->setVariable("TXT_TITLE", $d["title"]);
        $this->tpl->setVariable("TXT_TYPE", $d["type"]);
        $this->tpl->setVariable("ORDER_VAL", $d["order_val"]);
        
        $this->tpl->setVariable("TXT_EDIT", $lng->txt("edit"));
        $this->tpl->setVariable(
            "CMD_EDIT",
            $ilCtrl->getLinkTarget($this->parent_obj, "editAssignment")
        );
    }
}
