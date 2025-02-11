<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Class ilExerciseMembers
*
* @author Stefan Meyer <meyer@leifos.com>
*
* @ingroup ModulesExercise
*/
class ilExerciseMembers
{
    /**
     * @var ilDB
     */
    protected $db;

    public $ref_id;
    public $obj_id;
    public $members;
    public $status;
    //	var $status_feedback;
    //	var $status_sent;
    //	var $status_returned;
    //	var $notice;

    /**
     * @var ilRecommendedContentManager
     */
    protected $recommended_content_manager;

    public function __construct($a_exc)
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->exc = $a_exc;
        $this->obj_id = $a_exc->getId();
        $this->ref_id = $a_exc->getRefId();
        $this->read();

        $this->recommended_content_manager = new ilRecommendedContentManager();
    }

    /**
     * Get exercise ref id
     */
    public function getRefId()
    {
        return $this->ref_id;
    }

    /**
     * Get exercise id
     */
    public function getObjId()
    {
        return $this->obj_id;
    }
    
    /**
     * Set exercise id
     */
    public function setObjId($a_obj_id)
    {
        $this->obj_id = $a_obj_id;
    }

    /**
     * Get members array
     */
    public function getMembers()
    {
        return $this->members ? $this->members : array();
    }
    
    /**
     * Set members array
     */
    public function setMembers($a_members)
    {
        $this->members = $a_members;
    }

    /**
    * Assign a user to the exercise
    *
    * @param	int		$a_usr_id		user id
    */
    public function assignMember($a_usr_id)
    {
        $ilDB = $this->db;

        /*if($this->exc->hasAddToDesktop())
        {
        }*/

        $ilDB->manipulate("DELETE FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($this->getObjId(), "integer") . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, "integer") . " ");

        // @todo: some of this fields may not be needed anymore
        $ilDB->manipulateF(
            "INSERT INTO exc_members (obj_id, usr_id, status, sent, feedback) " .
            " VALUES (%s,%s,%s,%s,%s)",
            array("integer", "integer", "text", "integer", "integer"),
            array($this->getObjId(), $a_usr_id, 'notgraded', 0, 0)
        );

        ilExAssignment::createNewUserRecords($a_usr_id, $this->getObjId());
        
        $this->read();
        
        ilLPStatusWrapper::_updateStatus($this->getObjId(), $a_usr_id);

        return true;
    }
    
    /**
     * Is user assigned to exercise?
     */
    public function isAssigned($a_id)
    {
        return in_array($a_id, $this->getMembers());
    }

    /**
     * Assign members to exercise
     */
    public function assignMembers($a_members)
    {
        $assigned = 0;
        if (is_array($a_members)) {
            foreach ($a_members as $member) {
                if (!$this->isAssigned($member)) {
                    $this->assignMember($member);
                } else {
                    ++$assigned;
                }
            }
        }
        if ($assigned == count($a_members)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Detaches a user from an exercise
     *
     * @param	int		$a_usr_id		user id
     */
    public function deassignMember($a_usr_id)
    {
        $ilDB = $this->db;

        $this->recommended_content_manager->removeObjectRecommendation($a_usr_id, $this->getRefId());

        $query = "DELETE FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($this->getObjId(), "integer") . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, "integer") . " ";

        $ilDB->manipulate($query);
        
        $this->read();
        
        ilLPStatusWrapper::_updateStatus($this->getObjId(), $a_usr_id);
        
        // delete all delivered files of the member
        ilExSubmission::deleteUser($this->exc->getId(), $a_usr_id);

        // @todo: delete all assignment associations (and their files)
        
        return false;
    }

    /**
     * Deassign members
     */
    public function deassignMembers($a_members)
    {
        if (is_array($a_members)) {
            foreach ($a_members as $member) {
                $this->deassignMember($member);
            }
        } else {
            return false;
        }
    }

    /**
     * Read all members
     */
    public function read()
    {
        $ilDB = $this->db;

        $tmp_arr_members = array();

        $query = "SELECT * FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($this->getObjId(), "integer");

        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            if (ilObject::_lookupType($row->usr_id) == "usr") {
                $tmp_arr_members[] = $row->usr_id;
            }
        }
        $this->setMembers($tmp_arr_members);

        return true;
    }

    // @todo: clone also assignments
    public function ilClone($a_new_id)
    {
        $ilDB = $this->db;

        $data = array();

        $query = "SELECT * FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($this->getObjId(), "integer");

        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $data[] = array("usr_id" => $row->usr_id,
                            "notice" => $row->notice,
                            "returned" => $row->returned,
                            "status" => $row->status,
                            "sent" => $row->sent,
                            "feedback" => $row->feedback
                            );
        }
        foreach ($data as $row) {
            $ilDB->manipulateF(
                "INSERT INTO exc_members " .
                " (obj_id, usr_id, notice, returned, status, feedback, sent) VALUES " .
                " (%s,%s,%s,%s,%s,%s,%s)",
                array("integer", "integer", "text", "integer", "text", "integer", "integer"),
                array($a_new_id, $row["usr_id"], $row["notice"], (int) $row["returned"],
                    $row["status"], (int) $row["feedback"], (int) $row["sent"])
            );
            
            ilLPStatusWrapper::_updateStatus($a_new_id, $row["usr_id"]);
        }
        return true;
    }

    // @todo: delete also assignments
    public function delete()
    {
        $ilDB = $this->db;

        $query = "DELETE FROM exc_members WHERE obj_id = " .
            $ilDB->quote($this->getObjId(), "integer");
        $ilDB->manipulate($query);
        
        ilLPStatusWrapper::_refreshStatus($this->getObjId());

        return true;
    }

    public static function _getMembers($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        // #14963 - see ilExAssignment::getMemberListData()
        $query = "SELECT DISTINCT(excm.usr_id) ud" .
            " FROM exc_members excm" .
            " JOIN object_data od ON (od.obj_id = excm.usr_id)" .
            " WHERE excm.obj_id = " . $ilDB->quote($a_obj_id, "integer") .
            " AND od.type = " . $ilDB->quote("usr", "text");

        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $usr_ids[] = $row->ud;
        }

        return $usr_ids ? $usr_ids : array();
    }

    /**
     * Lookup current status (notgraded|passed|failed)
     *
     * This information is determined by the assignment status and saved
     * redundtantly in this table for performance reasons.
     *
     * @param	int		$a_obj_id	exercise id
     * @param	int		$a_user_id	member id
     * @return	mixed	false (if user is no member) or notgraded|passed|failed
     */
    public static function _lookupStatus($a_obj_id, $a_user_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $query = "SELECT status FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") .
            " AND usr_id = " . $ilDB->quote($a_user_id, "integer");

        $res = $ilDB->query($query);
        if ($row = $ilDB->fetchAssoc($res)) {
            return $row["status"];
        }

        return false;
    }

    // fau: exCalc - new function lookupStatusTime
    /**
     * Get the time from the last status update
     */
    public static function _lookupStatusTime($exc_id, $member_id)
    {
        global $ilDB, $lng;

        $q = "SELECT * " .
            "FROM exc_members " .
            "WHERE obj_id= " . $ilDB->quote($exc_id, "integer") .
            " AND usr_id= " . $ilDB->quote($member_id, "integer");

        $set = $ilDB->query($q);
        if ($rec = $ilDB->fetchAssoc($set)) {
            return ilUtil::getMySQLTimestamp($rec["status_time"]);
        }
    }
    // fau.

    /**
     * Write user status
     *
     * This information is determined by the assignment status and saved
     * redundtantly in this table for performance reasons.
     * See ilObjExercise->updateUserStatus().
     *
     * @param	int		exercise id
     * @param	int		user id
     * @param	text	status
     */
    public static function _writeStatus($a_obj_id, $a_user_id, $a_status)
    {
        global $DIC;

        $ilDB = $DIC->database();
        
        $ilDB->manipulate(
            "UPDATE exc_members SET " .
            " status = " . $ilDB->quote($a_status, "text") .
            // fau: exCalc - update the status time
            ", status_time = " . $ilDB->quote(ilUtil::now(), "timestamp") .
            // fau.
            " WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") .
            " AND usr_id = " . $ilDB->quote($a_user_id, "integer")
        );
        
        ilLPStatusWrapper::_updateStatus($a_obj_id, $a_user_id);
    }
    
    /**
     * Write returned status
     *
     * The returned status is initially 0. If the first file is returned
     * by a user for any assignment of the exercise, the returned status
     * is set to 1 and it will stay that way, even if this file is deleted again.
     * -> learning progress uses this to determine "in progress" status
     *
     * @param	int		exercise id
     * @param	int		user id
     * @param	text	status
     */
    public static function _writeReturned($a_obj_id, $a_user_id, $a_status)
    {
        global $DIC;

        $ilDB = $DIC->database();
        
        $ilDB->manipulate(
            "UPDATE exc_members SET " .
            " returned = " . $ilDB->quote($a_status, "integer") .
            " WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") .
            " AND usr_id = " . $ilDB->quote($a_user_id, "integer")
        );
        
        ilLPStatusWrapper::_updateStatus($a_obj_id, $a_user_id);
    }
    
    
    //
    // LP
    //
    
    /**
     * Get returned status for all members (if they have anything returned for
     * any assignment)
     */
    public static function _getReturned($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $query = "SELECT DISTINCT(usr_id) as ud FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") . " " .
            "AND returned = 1";

        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $usr_ids[] = $row->ud;
        }

        return $usr_ids ? $usr_ids : array();
    }

    /**
     * Has user returned anything in any assignment?
     *
     * @param		integer		object id
     * @param		integer		user id
     * @return		boolean		true/false
     */
    public static function _hasReturned($a_obj_id, $a_user_id)
    {
        global $DIC;

        $ilDB = $DIC->database();
    
        $set = $ilDB->query(
            "SELECT DISTINCT(usr_id) FROM exc_members WHERE " .
            " obj_id = " . $ilDB->quote($a_obj_id, "integer") . " AND " .
            " returned = " . $ilDB->quote(1, "integer") . " AND " .
            " usr_id = " . $ilDB->quote($a_user_id, "integer")
        );
        if ($rec = $ilDB->fetchAssoc($set)) {
            return true;
        }
        return false;
    }

    /**
     * Get all users that passed the exercise
     */
    public static function _getPassedUsers($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $query = "SELECT DISTINCT(usr_id) FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") . " " .
            "AND status = " . $ilDB->quote("passed", "text");
        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $usr_ids[] = $row->usr_id;
        }
        return $usr_ids ? $usr_ids : array();
    }

    /**
     * Get all users that failed the exercise
     */
    public static function _getFailedUsers($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $query = "SELECT DISTINCT(usr_id) FROM exc_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") . " " .
            "AND status = " . $ilDB->quote("failed", "text");
        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $usr_ids[] = $row->usr_id;
        }
        return $usr_ids ? $usr_ids : array();
    }
} //END class.ilObjExercise
