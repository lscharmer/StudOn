<?php

/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Object/classes/class.ilObjectAccess.php");
include_once './Modules/Course/classes/class.ilCourseConstants.php';
include_once 'Modules/Course/classes/class.ilCourseParticipants.php';
include_once 'Modules/Course/classes/class.ilCourseParticipant.php';
include_once './Services/Conditions/interfaces/interface.ilConditionHandling.php';

/**
* Class ilObjCourseAccess
*
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
*/
class ilObjCourseAccess extends ilObjectAccess implements ilConditionHandling
{
    protected static $using_code = false;

    /**
     * @var ilBookingReservationDBRepository
     */
    protected static $booking_repo = null;

    /**
     * Get operators
     */
    public static function getConditionOperators()
    {
        include_once './Services/Conditions/classes/class.ilConditionHandler.php';
        return array(
            ilConditionHandler::OPERATOR_PASSED
        );
    }

    /**
     *
     * @global ilObjUser $ilUser
     * @param type $a_obj_id
     * @param type $a_operator
     * @param type $a_value
     * @param type $a_usr_id
     * @return boolean
     */
    public static function checkCondition($a_obj_id, $a_operator, $a_value, $a_usr_id)
    {
        include_once "./Modules/Course/classes/class.ilCourseParticipants.php";
        include_once './Services/Conditions/classes/class.ilConditionHandler.php';
        
        switch ($a_operator) {
            case ilConditionHandler::OPERATOR_PASSED:
                return ilCourseParticipants::_hasPassed($a_obj_id, $a_usr_id);
        }
        return false;
    }
    
    /**
    * checks wether a user may invoke a command or not
    * (this method is called by ilAccessHandler::checkAccess)
    *
    * @param	string		$a_cmd		command (not permission!)
    * @param	string		$a_permission	permission
    * @param	int			$a_ref_id	reference id
    * @param	int			$a_obj_id	object id
    * @param	int			$a_user_id	user id (if not provided, current user is taken)
    *
    * @return	boolean		true, if everything is ok
    */
    public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        $lng = $DIC['lng'];
        $rbacsystem = $DIC['rbacsystem'];
        $ilAccess = $DIC['ilAccess'];
        $ilias = $DIC['ilias'];
        
        if ($a_user_id == "") {
            $a_user_id = $ilUser->getId();
        }
        
        if ($ilUser->getId() == $a_user_id) {
            $participants = ilCourseParticipant::_getInstanceByObjId($a_obj_id, $a_user_id);
        } else {
            $participants = ilCourseParticipants::_getInstanceByObjId($a_obj_id);
        }


        switch ($a_cmd) {
            case "view":
                if ($participants->isBlocked($a_user_id) and $participants->isAssigned($a_user_id)) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("crs_status_blocked"));
                    return false;
                }
                break;

            case 'leave':

                // Regular member
                if ($a_permission == 'leave') {
                    include_once './Modules/Course/classes/class.ilObjCourse.php';
                    $limit = null;
                    if (!ilObjCourse::mayLeave($a_obj_id, $a_user_id, $limit)) {
                        $ilAccess->addInfoItem(
                            ilAccessInfo::IL_STATUS_INFO,
                            sprintf($lng->txt("crs_cancellation_end_rbac_info"), ilDatePresentation::formatDate($limit))
                        );
                        return false;
                    }
                    
                    include_once './Modules/Course/classes/class.ilCourseParticipants.php';
                    if (!$participants->isAssigned($a_user_id)) {
                        return false;
                    }
                }
                // Waiting list
                if ($a_permission == 'join') {
                    include_once './Modules/Course/classes/class.ilCourseWaitingList.php';
                    if (!ilCourseWaitingList::_isOnList($a_user_id, $a_obj_id)) {
                        return false;
                    }
                    return true;
                }
                break;

            case 'join':

                include_once './Modules/Course/classes/class.ilCourseWaitingList.php';
                if (ilCourseWaitingList::_isOnList($a_user_id, $a_obj_id)) {
                    return false;
                }
                break;

            // fau: joinAsGuest - check rights for guest accounts to request a join
            case 'joinAsGuest':

                // don't show join_as_guest command if user is already assigned
                if ($participants->isAssigned($a_user_id)) {
                    return false;
                }

                // don't show join_as_guest command if user can join
                if ($rbacsystem->checkAccessOfUser($a_user_id, 'join', $a_ref_id)) {
                    return false;
                }
                break;
            // fau.
        }
        
        switch ($a_permission) {

            // fau: preventCampoDelete - check if course can be deleted - moving (cut) should be allowed
//            case 'delete':
//                if ($a_cmd != 'cut'
//                    && !$DIC->fau()->user()->canDeleteObjectsForCourses((int) $a_user_id)
//                    && $DIC->fau()->study()->isObjectForCampo((int) $a_obj_id)
//                ) {
//                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("fau_delete_course_blocked"));
//                    //log_line("delete blocked for ref $a_ref_id with command $a_cmd");
//                    return false;
//                }
//                break;
            // fau.
            case 'visible':
                $visible = null;
                $active = self::_isActivated($a_obj_id, $visible);
                $tutor = $rbacsystem->checkAccessOfUser($a_user_id, 'write', $a_ref_id);
                if (!$active) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                }
                if (!$tutor && !$active && !$visible) {
                    return false;
                }
                break;
            
            case 'read':
                $tutor = $rbacsystem->checkAccessOfUser($a_user_id, 'write', $a_ref_id);
                if ($tutor) {
                    return true;
                }
                $active = self::_isActivated($a_obj_id);
                if (!$active) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                    return false;
                }
                if ($participants->isBlocked($a_user_id) and $participants->isAssigned($a_user_id)) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("crs_status_blocked"));
                    return false;
                }
                break;
                
            case 'join':
                if (!self::_registrationEnabled($a_obj_id)) {
                    return false;
                }

// fau: fairSub - add waiting list check for join permission
                include_once './Modules/Course/classes/class.ilCourseWaitingList.php';
                if (ilCourseWaitingList::_isOnList($a_user_id, $a_obj_id)) {
                    return false;
                }
// fau.

                if ($participants->isAssigned($a_user_id)) {
                    return false;
                }
                break;
                
            case 'leave':
                include_once './Modules/Course/classes/class.ilObjCourse.php';
                return ilObjCourse::mayLeave($a_obj_id, $a_user_id);
        }
        return true;
    }

    /**
     * get commands
     *
     * this method returns an array of all possible commands/permission combinations
     *
     * example:
     * $commands = array
     *	(
     *		array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
     *		array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
     *	);
     */
    public static function _getCommands()
    {
        $commands = array();
        $commands[] = array("permission" => "crs_linked", "cmd" => "", "lang_var" => "view", "default" => true);

        $commands[] = array("permission" => "join", "cmd" => "join", "lang_var" => "join");

        // fau: fairSub - general command for editing requests
        // on waiting list
        $commands[] = array('permission' => "join", "cmd" => "leave", "lang_var" => "mem_edit_request");
        // fau.

        // fau: joinAsGuest - add command for guest accounts to request a join
        include_once('Services/User/classes/class.ilUserUtil.php');
        if (ilUserUtil::_isGuestHearer()) {
            $commands[] = array('permission' => "visible", "cmd" => "joinAsGuest", "lang_var" => "join_as_guest");
        }
        // fau.
        
        // regualar users
        $commands[] = array('permission' => "leave", "cmd" => "leave", "lang_var" => "crs_unsubscribe");

        include_once('Services/WebDAV/classes/class.ilDAVActivationChecker.php');
        if (ilDAVActivationChecker::_isActive()) {
            include_once './Services/WebDAV/classes/class.ilWebDAVUtil.php';
            if (ilWebDAVUtil::getInstance()->isLocalPasswordInstructionRequired()) {
                $commands[] = array('permission' => 'read', 'cmd' => 'showPasswordInstruction', 'lang_var' => 'mount_webfolder', 'enable_anonymous' => 'false');
            } else {
                $commands[] = array("permission" => "read", "cmd" => "mount_webfolder", "lang_var" => "mount_webfolder", "enable_anonymous" => "false");
            }
        }

        $commands[] = array("permission" => "write", "cmd" => "enableAdministrationPanel", "lang_var" => "edit_content");
        $commands[] = array("permission" => "write", "cmd" => "edit", "lang_var" => "settings");
        return $commands;
    }
    
    /**
    * check whether goto script will succeed
    */
    public static function _checkGoto($a_target)
    {
        global $DIC;

        $ilAccess = $DIC['ilAccess'];
        $ilUser = $DIC['ilUser'];
        
        $t_arr = explode("_", $a_target);
        
        // registration codes
        if (substr($t_arr[2], 0, 5) == 'rcode' and $ilUser->getId() != ANONYMOUS_USER_ID) {
            self::$using_code = true;
            return true;
        }
        

        if ($t_arr[0] != "crs" || ((int) $t_arr[1]) <= 0) {
            return false;
        }

        // fau: joinLink - don't allow 'join' command for anonymous users
        if ($t_arr[2] == 'join' && $ilUser->getId() == ANONYMOUS_USER_ID) {
            global $lng;

            // ugly fix: $tpl used by ilUtil may not be initialized
            // ilUtil::sendInfo($lng->txt('join_crs_needs_login'), true);
            //ilTemplate::MESSAGE_TYPE_INFO
            $_SESSION['info'] = $lng->txt('join_crs_needs_login');
            ilUtil::redirect(ilUtil::_getLoginLink($a_target), true);
        }
        // fau.

        // checking for read results in endless loop, if read is given
        // but visible is not given (-> see bug 5323)
        if ($ilAccess->checkAccess("read", "", $t_arr[1]) ||
            $ilAccess->checkAccess("visible", "", $t_arr[1])) {
            //if ($ilAccess->checkAccess("visible", "", $t_arr[1]))
            return true;
        }
        return false;
    }
    
    /**
     * Lookup view mode. This is placed here to the need that ilObjFolder must
     * always instantiate a Course object.
     * @return
     * @param object $a_id
     */
    public static function _lookupViewMode($a_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT view_mode FROM crs_settings WHERE obj_id = " . $ilDB->quote($a_id, 'integer') . " ";
        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            return $row->view_mode;
        }
        return false;
    }
    
    /**
     * Is activated?
     *
     * @see ilStartupGUI
     * @param int $a_obj_id
     * @param bool &$a_visible_flag
     * @param bool $a_mind_member_view
     * @return boolean
     */
    public static function _isActivated($a_obj_id, &$a_visible_flag = null, $a_mind_member_view = true)
    {
        // #7669
        if ($a_mind_member_view) {
            include_once './Services/Container/classes/class.ilMemberViewSettings.php';
            if (ilMemberViewSettings::getInstance()->isActive()) {
                $a_visible_flag = true;
                return true;
            }
        }
        
        $ref_id = ilObject::_getAllReferences($a_obj_id);
        $ref_id = array_pop($ref_id);
        
        $a_visible_flag = true;
        
        include_once './Services/Object/classes/class.ilObjectActivation.php';
        $item = ilObjectActivation::getItem($ref_id);
        switch ($item['timing_type']) {
            case ilObjectActivation::TIMINGS_ACTIVATION:
                if (time() < $item['timing_start'] or
                   time() > $item['timing_end']) {
                    $a_visible_flag = $item['visible'];
                    return false;
                }
                // fallthrough
                
                // no break
            default:
                return true;
        }
    }

    /**
     *
     * @return
     * @param object $a_obj_id
     */
    public static function _registrationEnabled($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM crs_settings " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " ";

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $type = $row->sub_limitation_type;
            $reg_start = $row->sub_start;
            $reg_end = $row->sub_end;
        }

        switch ($type) {
// fau: campusSub - set registration enabled if type is mycampus
            case IL_CRS_SUBSCRIPTION_MYCAMPUS:
                return true;
// fau.

            case IL_CRS_SUBSCRIPTION_UNLIMITED:
                return true;

            case IL_CRS_SUBSCRIPTION_DEACTIVATED:
                return false;

            case IL_CRS_SUBSCRIPTION_LIMITED:
                if (time() > $reg_start and
                   time() < $reg_end) {
                    return true;
                }
                // no break
            default:
                return false;
        }
        return false;
    }
    
    /**
     * Lookup registration info
     * @global ilDB $ilDB
     * @global ilObjUser $ilUser
     * @global ilLanguage $lng
     * @param int $a_obj_id
     * @return array
     */
    // fau: showMemLimit - add ref_id as parameter for checking write access
    public static function lookupRegistrationInfo($a_obj_id, $a_ref_id = 0)
    // fau.
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $ilUser = $DIC['ilUser'];
        $lng = $DIC['lng'];

        // fau: fairSub - query for fair period
        $query = 'SELECT sub_limitation_type, sub_start, sub_end, sub_mem_limit, sub_max_members, sub_fair FROM crs_settings ' .
            'WHERE obj_id = ' . $ilDB->quote($a_obj_id);
        $res = $ilDB->query($query);
        
        $info = array();
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $info['reg_info_start'] = new ilDateTime($row->sub_start, IL_CAL_UNIX);
            $info['reg_info_end'] = new ilDateTime($row->sub_end, IL_CAL_UNIX);
            $info['reg_info_type'] = $row->sub_limitation_type;
            $info['reg_info_max_members'] = $row->sub_max_members;
            $info['reg_info_mem_limit'] = $row->sub_mem_limit;
            $info['reg_info_sub_fair'] = $row->sub_fair;
        }
        // fau.

        $registration_possible = true;

        // Limited registration
        if ($info['reg_info_type'] == ilCourseConstants::SUBSCRIPTION_LIMITED) {
            // fau: fairSub - add info about fair period
            $fair_suffix = '';
            if ($info['reg_info_mem_limit'] > 0 && $info['reg_info_max_members'] > 0) {
                if ($info['reg_info_sub_fair'] < 0) {
                    $fair_suffix = " - <b>" . $lng->txt('sub_fair_inactive_short') . "</b>";
                }
//	            elseif (time() < $info['reg_info_sub_fair'])
//				{
//					$fair_suffix = " <br />".$lng->txt('sub_fair_date'). ': '
//						. ilDatePresentation::formatDate(new ilDateTime($info['reg_info_sub_fair'],IL_CAL_UNIX));
//				}
            }

            $dt = new ilDateTime(time(), IL_CAL_UNIX);
            if (ilDateTime::_before($dt, $info['reg_info_start'])) {
                $info['reg_info_list_prop']['property'] = $lng->txt('crs_list_reg_start');
                $info['reg_info_list_prop']['value'] = ilDatePresentation::formatDate($info['reg_info_start']) . $fair_suffix;
            } elseif (ilDateTime::_before($dt, $info['reg_info_end'])) {
                $info['reg_info_list_prop']['property'] = $lng->txt('crs_list_reg_end');
                $info['reg_info_list_prop']['value'] = ilDatePresentation::formatDate($info['reg_info_end']) . $fair_suffix;
            } else {
                $registration_possible = false;
                $info['reg_info_list_prop']['property'] = $lng->txt('crs_list_reg_period');
                $info['reg_info_list_prop']['value'] = $lng->txt('crs_list_reg_noreg');
            }
            // fau.
        } elseif ($info['reg_info_type'] == ilCourseConstants::SUBSCRIPTION_UNLIMITED) {
            $registration_possible = true;
        } else {
            $registration_possible = false;
            // fau: showRegLimit - hide registration info if registration is not possible (exam platforms)
            // $info['reg_info_list_prop']['property'] = $lng->txt('crs_list_reg');
            // $info['reg_info_list_prop']['value'] = $lng->txt('crs_list_reg_noreg');
            // fau.
        }

        // fau: showMemLimit - get info about membership limitations and subscription status
        global $ilAccess;
        include_once './Modules/Course/classes/class.ilCourseParticipant.php';
        include_once './Modules/Course/classes/class.ilCourseWaitingList.php';

        $partObj = ilCourseParticipant::_getInstanceByObjId($a_obj_id, $ilUser->getId());

        if ($info['reg_info_mem_limit'] && $registration_possible) {
            $show_mem_limit = true;
            $show_hidden_notice = false;
        } elseif ($info['reg_info_mem_limit'] && $ilAccess->checkAccess('write', '', $a_ref_id, 'crs', $a_obj_id)) {
            $show_mem_limit = true;
            $show_hidden_notice = true;
        } else {
            $show_mem_limit = false;
            $show_hidden_notice = false;
        }

        // this must always be calculeted because it is used for the info and registration page
        $max_members = $info['reg_info_max_members'];
        $members = (int) $partObj->getNumberOfMembers();
        $free_places = max($max_members - $members, 0);
        $info['reg_info_free_places'] = $free_places;

        if ($show_mem_limit) {
            $waiting = ilCourseWaitingList::lookupListSize($a_obj_id);

            $limits = array();
            $limits[] = $lng->txt("mem_max_users") . $max_members;
            $limits[] = $lng->txt("mem_free_places") . ': ' . $free_places;
            if ($waiting > 0) {
                $limits[] = $lng->txt("subscribers_or_waiting_list") . ': ' . (string) ($waiting);
            }

            if ($show_hidden_notice) {
                $info['reg_info_list_prop_limit']['property'] = $lng->txt("mem_max_users_hidden");
            }
            else {
                $info['reg_info_list_prop_limit']['property'] = '';
            }
            $info['reg_info_list_prop_limit']['value'] = implode(' &nbsp; ', $limits);
        }

        // registration status
        switch (ilCourseWaitingList::_getStatus($ilUser->getId(), $a_obj_id)) {
            case ilWaitingList::REQUEST_NOT_TO_CONFIRM:
                $status = $lng->txt('on_waiting_list');
                break;
            case ilWaitingList::REQUEST_TO_CONFIRM:
                $status = $lng->txt('sub_status_pending');
                break;
            case ilWaitingList::REQUEST_CONFIRMED:
                $status = $lng->txt('sub_status_confirmed');
                break;
            default:
                $status = '';
        }
        if ($status) {
            $info['reg_info_list_prop_status']['property'] = $lng->txt('member_status');
            $info['reg_info_list_prop_status']['value'] = $status;
        }
        // fau.

        return $info;
    }

    /**
     * Type-specific implementation of general status
     *
     * Used in ListGUI and Learning Progress
     *
     * @param int $a_obj_id
     * @return bool
     */
    public static function _isOffline($a_obj_id)
    {
        $dummy = null;
        return !self::_isActivated($a_obj_id, $dummy, false);
    }
    
    /**
     * Preload data
     *
     * @param array $a_obj_ids array of object ids
     */
    public static function _preloadData($a_obj_ids, $a_ref_ids)
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        $lng = $DIC['lng'];

        $lng->loadLanguageModule("crs");
        
        ilCourseWaitingList::_preloadOnListInfo($ilUser->getId(), $a_obj_ids);
        
        $repository = new ilUserCertificateRepository();
        $coursePreload = new ilCertificateObjectsForUserPreloader($repository);
        $coursePreload->preLoad($ilUser->getId(), $a_obj_ids);

        $f = new ilBookingReservationDBRepositoryFactory();
        self::$booking_repo = $f->getRepoWithContextObjCache($a_obj_ids);
    }

    /**
     * Get booking info repo
     * @return ilBookingReservationDBRepository
     */
    public static function getBookingInfoRepo()
    {
        return self::$booking_repo;
    }


    /**
     * Using Registration code
     *
     * @return bool
     */
    public static function _usingRegistrationCode()
    {
        return self::$using_code;
    }

    /**
     * Lookup course period info
     *
     * @param int $a_obj_id
     * @return array
     */
    public static function lookupPeriodInfo($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $lng = $DIC['lng'];
        
        $start = $end = null;
        $query = 'SELECT period_start, period_end, period_time_indication FROM crs_settings ' .
            'WHERE obj_id = ' . $ilDB->quote($a_obj_id);

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(\ilDBConstants::FETCHMODE_OBJECT)) {
            if (!$row->period_time_indication) {
                $start = ($row->period_start
                    ? new \ilDate($row->period_start, IL_CAL_DATETIME)
                    : null);
                $end = ($row->period_end
                    ? new \ilDate($row->period_end, IL_CAL_DATETIME)
                    : null);
            } else {
                $start = ($row->period_start
                    ? new \ilDateTime($row->period_start, IL_CAL_DATETIME, \ilTimeZone::UTC)
                    : null);
                $end = ($row->period_end
                    ? new \ilDateTime($row->period_end, IL_CAL_DATETIME, \ilTimeZone::UTC)
                    : null);
            }
        }
        if ($start && $end) {
            $lng->loadLanguageModule('crs');
            
            return
                [
                    'crs_start' => $start,
                    'crs_end' => $end,
                    'property' => $lng->txt('crs_period'),
                    'value' => ilDatePresentation::formatPeriod($start, $end)
            ];
        }
    }
}
