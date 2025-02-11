<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Base class for course and group participants
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ServicesMembership
*/

define("IL_CRS_ADMIN", 1);
define("IL_CRS_TUTOR", 3);
define("IL_CRS_MEMBER", 2);

define('IL_GRP_ADMIN', 4);
define('IL_GRP_MEMBER', 5);

define('IL_SESS_MEMBER', 6);

define('IL_LSO_ADMIN', 7);
define('IL_LSO_MEMBER', 8);

define("IL_ROLE_POSITION_ADMIN", 1);
define("IL_ROLE_POSITION_TUTOR", 2);
define("IL_ROLE_POSITION_MEMBER", 3);


abstract class ilParticipants
{
    protected $component = '';
    
    protected $obj_id = 0;
    protected $type = '';
    protected $ref_id = 0;
    
    protected $roles = array();
    protected $role_data = array();
    protected $roles_sorted = [];
    protected $role_assignments = [];
    
    protected $participants = array();
    protected $participants_status = array();
    protected $members = array();
    protected $tutors = array();
    protected $admins = array();
    
    protected $subscribers = array();
    
    /**
     * @var ilDBInterface
     */
    protected $ilDB;
    
    /**
     * @var ilLanguage
     */
    protected $lng;
    
    /**
     *
     * @var \ilLogger
     */
    protected $logger = null;

    /**
     * @var ilRecommendedContentManager
     */
    protected $recommended_content_manager;

    /**
     * Singleton Constructor
     *
     * @access public
     * @param string component definition e.g Modules/Course
     * @param int obj_id of container
     *
     */
    public function __construct($a_component_name, $a_ref_id)
    {
        $this->ilDB = $GLOBALS['DIC']->database();
        $this->lng = $GLOBALS['DIC']->language();
        $this->logger = $GLOBALS['DIC']->logger()->mem();
     
        $this->component = $a_component_name;
        
        $this->ref_id = $a_ref_id;
        $this->obj_id = ilObject::_lookupObjId($a_ref_id);
        $this->type = ilObject::_lookupType($this->obj_id);
        
        $this->readParticipants();
        $this->readParticipantsStatus();
        $this->recommended_content_manager = new ilRecommendedContentManager();
    }
    
    /**
     * Get instance by ref_id
     * @param int $a_ref_id
     * @return ilParticipants
     */
    public static function getInstance($a_ref_id)
    {
        $obj_id = ilObject::_lookupObjId($a_ref_id);
        $type = ilObject::_lookupType($obj_id);
        
        switch ($type) {
            case 'crs':
            case 'grp':
            case 'lso':
                return self::getInstanceByObjId($obj_id);
            case 'sess':
                include_once './Modules/Session/classes/class.ilSessionParticipants.php';
                return ilSessionParticipants::getInstance($a_ref_id);
            default:
                $GLOBALS['DIC']->logger()->mem()->logStack();
                $GLOBALS['DIC']->logger()->mem()->warning('Invalid ref_id -> obj_id given: ' . $a_ref_id . ' -> ' . $obj_id);
                throw new \InvalidArgumentException('Invalid obj_id given.');
        }
    }
    
    /**
     * Get instance by obj type
     *
     * @param int $a_obj_id
     * @return ilParticipants
     * @throws InvalidArgumentException
     * @deprecated since version 5.4 use getInstance() (ref_id based)
     */
    public static function getInstanceByObjId($a_obj_id)
    {
        $type = ilObject::_lookupType($a_obj_id);
        switch ($type) {
            case 'crs':
                include_once './Modules/Course/classes/class.ilCourseParticipants.php';
                return ilCourseParticipants::_getInstanceByObjId($a_obj_id);
                
            case 'grp':
                include_once './Modules/Group/classes/class.ilGroupParticipants.php';
                return ilGroupParticipants::_getInstanceByObjId($a_obj_id);
                
            case 'sess':
                include_once './Modules/Session/classes/class.ilSessionParticipants.php';
                return ilSessionParticipants::_getInstanceByObjId($a_obj_id);
            case 'lso':
                return ilLearningSequenceParticipants::_getInstanceByObjId($a_obj_id);
            default:
                $GLOBALS['DIC']->logger()->mmbr()->logStack(ilLogLevel::WARNING);
                $GLOBALS['DIC']->logger()->mmbr()->warning(': Invalid obj_id given: ' . $a_obj_id);
                throw new InvalidArgumentException('Invalid obj id given');
        }
    }
    
    /**
     * Get component name
     * Used for raising events
     */
    protected function getComponent()
    {
        return $this->component;
    }
    

    
    /**
     * Check if (current) user has access to the participant list
     * @param int $a_obj
     * @param int $a_usr_id
     */
    public static function hasParticipantListAccess($a_obj_id, $a_usr_id = null)
    {
        global $DIC;

        $access = $DIC->access();

        if (!$a_usr_id) {
            $a_usr_id = $GLOBALS['DIC']['ilUser']->getId();
        }

        // if write access granted => return true
        $refs = ilObject::_getAllReferences($a_obj_id);
        $ref_id = end($refs);

        if ($access->checkAccess('manage_members', '', $ref_id)) {
            return true;
        }
        $part = self::getInstance($ref_id);
        if ($part->isAssigned($a_usr_id)) {
            if ($part->getType() == 'crs') {
                if (!ilObjCourse::lookupShowMembersEnabled($a_obj_id)) {
                    return false;
                }
            }
            if ($part->getType() == 'grp') {
                if (!ilObjGroup::lookupShowMembersEnabled($a_obj_id)) {
                    return false;
                }
            }
            return true;
        }
        // User is not assigned to course/group => no read access
        return false;
    }


    /**
     * Get user membership assignments by type
     *
     * @param int[] $a_user_ids
     * @param string[] $a_type
     * @param bool $a_only_member_roles
     */
    public static function getUserMembershipAssignmentsByType($a_user_ids, $a_type, $a_only_member_roles)
    {
        global $DIC;

        $logger = $DIC->logger()->mmbr();
        $ilDB = $DIC->database();

        if ($a_only_member_roles) {
            $j2 = "JOIN object_data obd2 ON (ua.rol_id = obd2.obj_id) ";
            $a2 = 'AND obd2.title = ' . $ilDB->concat(
                array(
                        array($ilDB->quote('il_', 'text')),
                        array('obd.type'),
                        array($ilDB->quote('_member_', 'text')),
                        array('obr.ref_id'),
                    ),
                false
                );
        }

        $query = "SELECT DISTINCT obd.obj_id,obr.ref_id,ua.usr_id FROM rbac_ua ua " .
            "JOIN rbac_fa fa ON ua.rol_id = fa.rol_id " .
            "JOIN object_reference obr ON fa.parent = obr.ref_id " .
            "JOIN object_data obd ON obr.obj_id = obd.obj_id " .
            $j2 .
            "WHERE " . $ilDB->in("obd.type", $a_type, false, "text") .
            "AND fa.assign = 'y' " .
            'AND ' . $ilDB->in('ua.usr_id', $a_user_ids, false, 'integer') . ' ' .
            $a2;
        
        $obj_ids = [];
        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $obj_ids[$row->obj_id][] = $row->usr_id;
        }

        $logger->dump($obj_ids, \ilLogLevel::DEBUG);

        return $obj_ids;
    }

    /**
     * get membership by type
     * Get course or group membership
     *
     * @access public
     * @param int $a_usr_id usr_id
     * @param string|array $a_type crs or grp | array of strings
     * @param bool $a_only_member_role
     * @return
     * @static
     */
    public static function _getMembershipByType($a_usr_id, $a_type, $a_only_member_role = false)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if (!is_array($a_type)) {
            $a_type = array($a_type);
        }

        // this will also dismiss local roles!
        if ($a_only_member_role) {
            $j2 = "JOIN object_data obd2 ON (ua.rol_id = obd2.obj_id) ";
            $a2 = 'AND obd2.title = ' . $ilDB->concat(
                array(
                    array($ilDB->quote('il_', 'text')),
                    array('obd.type'),
                    array($ilDB->quote('_member_', 'text')),
                    array('obr.ref_id'),
                ),
                false
            );
        }

        // #14290 - no role folder anymore
        $query = "SELECT DISTINCT obd.obj_id,obr.ref_id FROM rbac_ua ua " .
            "JOIN rbac_fa fa ON ua.rol_id = fa.rol_id " .
            "JOIN object_reference obr ON fa.parent = obr.ref_id " .
            "JOIN object_data obd ON obr.obj_id = obd.obj_id " .
            $j2 .
            "WHERE " . $ilDB->in("obd.type", $a_type, false, "text") . ' ' .
            "AND fa.assign = 'y' " .
            "AND ua.usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            'AND obr.deleted IS NULL ' .
            $a2;
        $res = $ilDB->query($query);
        while ($row = $ilDB->fetchObject($res)) {
            $ref_ids[] = $row->obj_id;
        }
        
        return $ref_ids ? $ref_ids : array();
    }
    
    
    
    /**
     * Static function to check if a user is a participant of the container object
     *
     * @access public
     * @param int ref_id
     * @param int user id
     * @static
     */
    public static function _isParticipant($a_ref_id, $a_usr_id)
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];

        $local_roles = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);
        
        return $rbacreview->isAssignedToAtLeastOneGivenRole($a_usr_id, $local_roles);
    }

    // fau: mailToMembers - new function _isTutor()
    /**
     * Static function to check if a user is tutor of a course
     * This is used for the special case where course tutors should be allowed to send a mail to the members
     * @param int $a_ref_id
     * @param int $a_usr_id
     * @return bool
     */
    public static function _isTutor($a_ref_id, $a_usr_id)
    {
        global $DIC;
        $assigned_tutor_roles = $DIC->rbac()->review()->getRolesByFilter(0, $a_usr_id, 'il_crs_tutor_' . $a_ref_id);
        return !empty($assigned_tutor_roles);
    }
    // fau.

    // fau: mailToMembers - new function _isLocalOrUpperAdmin()
    /**
     * Static function to check if the user has an admin role of this or an upper course/group
     * This is used for the special case of nested groups where admins should see the member gallery
     * without having write access in the groups
     *
     * @param int $a_ref_id
     * @param int $a_usr_id
     * @return bool
     */
    public static function _isLocalOrUpperAdmin($a_ref_id, $a_usr_id)
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();
        /** @var ilTree $tree */
        $tree = $DIC['tree'];

        // check objects from current object upwards
        $path_ids = array_reverse($tree->getPathId($a_ref_id));
        foreach ($path_ids as $path_id) {
            // this gets all roles with local policies
            // it doen't get parent roles with protected permissions
            // so the parent objects have to be checked explictly
            $roles = $rbacreview->getRoleListByObject($path_id);
            foreach ($roles as $role_data) {
                if (substr($role_data['title'], 0, 13) == 'il_grp_admin_' || substr($role_data['title'], 0, 13) == 'il_crs_admin_') {
                    // assigned checks are cached so it doesn't matter if roles are checked twice
                    if ($rbacreview->isAssigned($a_usr_id, $role_data['obj_id'])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    // fau.

    /**
     * Lookup the number of participants (crs admins, tutors, members, grp admins, members)
     *
     * @global ilRbacReview $rbacreview
     * @param int $a_ref_id
     *
     */
    public static function lookupNumberOfParticipants($a_ref_id)
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];

        $lroles = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);
        return $rbacreview->getNumberOfAssignedUsers($lroles);
    }

    /**
     * Lookup number of members
     * @global ilRbacReview $rbacreview
     * @global <type> $ilObjDataCache
     * @param <type> $a_ref_id
     * @return int
     */
    public static function lookupNumberOfMembers($a_ref_id)
    {
        global $DIC;

        $rbacreview = $DIC->rbac()->review();
        $ilObjDataCache = $DIC['ilObjDataCache'];

        $has_policies = $rbacreview->getLocalPolicies($a_ref_id);

        if (!$has_policies) {
            return 0;
        }
        $lroles = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);

        $memberRoles = array();
        foreach ($lroles as $role_id) {
            $title = $ilObjDataCache->lookupTitle($role_id);
            switch (substr($title, 0, 8)) {
                case 'il_crs_a':
                case 'il_crs_t':
                case 'il_grp_a':
                    break;

                // fau: limitMemberRoles - don't count others roles as members
                case 'il_crs_m':
                case 'il_grp_m':
                    $memberRoles[] = $role_id;
                    break;
                // fau.
            }
        }
        return $rbacreview->getNumberOfAssignedUsers($memberRoles);
    }


    /**
     * Check if user is blocked
     *
     * @access public
     * @static
     *
     * @param int course id
     * @param int usr_id
     */
    public static function _isBlocked($a_obj_id, $a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND blocked = " . $ilDB->quote(1, 'integer');
        $res = $ilDB->query($query);
        return $res->numRows() ? true : false;
    }
    
    /**
     * Check if user has passed course
     *
     * @access public
     * @static
     *
     * @param int obj_id
     * @param int user id
     */
    public static function _hasPassed($a_obj_id, $a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND passed = '1'";
        $res = $ilDB->query($query);
        return $res->numRows() ? true : false;
    }
    
    /**
     * Delete all entries
     * Normally called for course deletion
     *
     * @access public
     * @static
     *
     * @param int obj_id
     */
    public static function _deleteAllEntries($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "DELETE FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);

        $query = "DELETE FROM il_subscribers " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . "";
        $res = $ilDB->manipulate($query);
        
        $query = 'DELETE FROM crs_waiting_list ' .
                'WHERE obj_id = ' . $ilDB->quote($a_obj_id, 'integer');
        $ilDB->manipulate($query);

        return true;
    }
    
    /**
     * Delete user data
     *
     * @access public
     * @static
     *
     * @param int user id
     */
    public static function _deleteUser($a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "DELETE FROM obj_members WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . "";
        $res = $ilDB->manipulate($query);

        $query = "DELETE FROM il_subscribers WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . "";
        $res = $ilDB->manipulate($query);

        include_once './Modules/Course/classes/class.ilCourseWaitingList.php';
        ilCourseWaitingList::_deleteUser($a_usr_id);
    }
    
    public static function getDefaultMemberRole($a_ref_id)
    {
        global $DIC;

        $ilCtrl = $DIC['ilCtrl'];
        
        $obj_id = ilObject::_lookupObjId($a_ref_id);
        $type = ilObject::_lookupType($obj_id);
        
        if (!in_array($type, array('crs','grp'))) {
            return 0;
        }
        
        global $DIC;

        $rbacreview = $DIC['rbacreview'];
        

        $roles = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);
        
        foreach ($roles as $role) {
            $title = ilObject::_lookupTitle($role);
            if (substr($title, 0, 13) == ('il_' . $type . '_member')) {
                return $role;
            }
        }
        return 0;
    }
        
    /**
     * get current obj_id
     * @return type
     */
    public function getObjId()
    {
        return $this->obj_id;
    }
    
    /**
     * Get object type
     * @return string obj_type
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * Get admin, tutor which have notification enabled
     *
     * @access public
     * @return int[] of user ids
     */
    public function getNotificationRecipients()
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $query = "SELECT * FROM obj_members " .
            "WHERE notification = 1 " .
            "AND obj_id = " . $ilDB->quote($this->obj_id) . " ";
        $res = $ilDB->query($query);
        $recp = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            if ($this->isAdmin($row->usr_id) or $this->isTutor($row->usr_id)) {
                $recp[] = $row->usr_id;
            }
        }
        return $recp;
    }
    
    /**
     * Get number of members (not participants)
     *
     * @access public
     *
     */
    public function getCountMembers()
    {
        return count($this->members);
    }
    
    /**
     * Get number of participants
     *
     * @access public
     *
     */
    public function getCountParticipants()
    {
        return count($this->participants);
    }
    
    
    
    
    /**
     * Get all participants ids
     *
     * @access public
     * @return array array of user ids
     */
    public function getParticipants()
    {
        return $this->participants ? $this->participants : array();
    }
    
    /**
     * Get all members ids (admins and tutors are not members)
     * Use get participants to fetch all
     *
     * @access public
     * @return array array of user ids
     */
    public function getMembers()
    {
        return $this->members ? $this->members : array();
    }
    /**
     * Get all admins ids
     *
     * @access public
     * @return array array of user ids
     */
    public function getAdmins()
    {
        return $this->admins ? $this->admins : array();
    }
    
    /**
     * Get number of admins
     * @return
     */
    public function getCountAdmins()
    {
        return count($this->getAdmins());
    }
    
    
    /**
     * Get all tutors ids
     *
     * @access public
     * @return array array of user ids
     */
    public function getTutors()
    {
        return $this->tutors ? $this->tutors : array();
    }
    
    /**
     * is user admin
     *
     * @access public
     * @param int usr_id
     *
     */
    public function isAdmin($a_usr_id)
    {
        return in_array($a_usr_id, $this->admins) ? true : false;
    }
    
    /**
     * is user tutor
     *
     * @access public
     * @param int usr_id
     *
     */
    public function isTutor($a_usr_id)
    {
        return in_array($a_usr_id, $this->tutors) ? true : false;
    }
    
    /**
     * is user member
     *
     * @access public
     * @param int usr_id
     *
     */
    public function isMember($a_usr_id)
    {
        return in_array($a_usr_id, $this->members) ? true : false;
    }
    
    
    
    
    /**
     * check if user is assigned
     *
     * @access public
     * @param
     *
     */
    public function isAssigned($a_usr_id)
    {
        return in_array($a_usr_id, $this->participants);
    }
    
    /**
     * Check if user is last admin
     * @param int $a_usr_id
     * @return bool
     */
    public function isLastAdmin($a_usr_id)
    {
        return in_array($a_usr_id, $this->getAdmins()) and count($this->getAdmins()) == 1;
    }
    
    
    /**
     * Get course roles
     *
     * @access public
     * @param
     *
     */
    public function getRoles()
    {
        return $this->roles ? $this->roles : array();
    }
    
    
    /**
     * fau: heavySub - get the actual role id of a role type
     * fau: fauService - get role id for role matching in sync
     *
     * needed to check membership and count of members
     * to avoid overbooking by heavy traffic
     *
     * @param 	integer		role type constant, e.g. IL_GRP_MEMBER
     * @return	integer		actual role id
     */
    public function getRoleId($a_role_type)
    {
        return $this->role_data[$a_role_type];
    }
    // fau.
    
    
    /**
     * Get assigned roles
     *
     * @access public
     * @param int user_id
     *
     */
    public function getAssignedRoles($a_usr_id)
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];
        
        foreach ($this->roles as $role) {
            if ($rbacreview->isAssigned($a_usr_id, $role)) {
                $assigned[] = $role;
            }
        }
        return $assigned ? $assigned : array();
    }
    
    /**
     * Update role assignments
     *
     * @access public
     * @param int usr_id
     * @param array array of new roles
     *
     */
    public function updateRoleAssignments($a_usr_id, $a_roles)
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];
        $rbacadmin = $DIC['rbacadmin'];
        
        $roles = $a_roles ? $a_roles : array();
        
        foreach ($this->getRoles() as $role_id) {
            if ($rbacreview->isAssigned($a_usr_id, $role_id)) {
                if (!in_array($role_id, $roles)) {
                    $rbacadmin->deassignUser($role_id, $a_usr_id);
                }
            } else {
                if (in_array($role_id, $roles)) {
                    $rbacadmin->assignUser($role_id, $a_usr_id);
                }
            }
        }
        $rbacreview->clearCaches();
        $this->readParticipants();
        $this->readParticipantsStatus();
    }
    
    /**
     * Check if user for deletion are last admins
     *
     * @access public
     * @param array array of user ids for deletion
     *
     */
    public function checkLastAdmin($a_usr_ids)
    {
        foreach ($this->getAdmins() as $admin_id) {
            if (!in_array($admin_id, $a_usr_ids)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user is blocked
     *
     * @access public
     * @param int user_id
     *
     */
    public function isBlocked($a_usr_id)
    {
        if (isset($this->participants_status[$a_usr_id])) {
            return $this->participants_status[$a_usr_id]['blocked'] ? true : false;
        }
        return false;
    }
    
    /**
     * Check if user has passed course
     *
     * @access public
     * @param int user_id
     *
     */
    public function hasPassed($a_usr_id)
    {
        if (isset($this->participants_status[$a_usr_id])) {
            return $this->participants_status[$a_usr_id]['passed'] ? true : false;
        }
        return false;
    }
    
    /**
     * Drop user from all roles
     *
     * @access public
     * @param int usr_id
     *
     */
    public function delete($a_usr_id)
    {
        global $DIC;

        $rbacadmin = $DIC['rbacadmin'];
        $ilDB = $DIC['ilDB'];

        $this->recommended_content_manager->removeObjectRecommendation($a_usr_id, $this->ref_id);

        foreach ($this->roles as $role_id) {
            $rbacadmin->deassignUser($role_id, $a_usr_id);
        }
        
        $query = "DELETE FROM obj_members " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer');
        $res = $ilDB->manipulate($query);
        
        $this->readParticipants();
        $this->readParticipantsStatus();
        
        $GLOBALS['DIC']['ilAppEventHandler']->raise(
            $this->getComponent(),
            "deleteParticipant",
            array(
                    'obj_id' => $this->obj_id,
                    'usr_id' => $a_usr_id,
                    // fau: fairSub - add type info to 'deleteParticipant' event in order to trigger the autofill in courses and groups
                    'type' => $this->type
                    // fau.
            ),
        );
        
        return true;
    }

    /**
     * Update blocked status
     *
     * @access public
     * @param int usr_id
     * @param bool blocked
     *
     */
    public function updateBlocked($a_usr_id, $a_blocked)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $this->participants_status[$a_usr_id]['blocked'] = (int) $a_blocked;

        $query = "SELECT * FROM obj_members " .
        "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
        "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        $res = $ilDB->query($query);
        if ($res->numRows()) {
            $query = "UPDATE obj_members SET " .
                "blocked = " . $ilDB->quote((int) $a_blocked, 'integer') . " " .
                "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
                "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        } else {
            $query = "INSERT INTO obj_members (blocked,obj_id,usr_id,notification,passed) " .
                "VALUES ( " .
                $ilDB->quote((int) $a_blocked, 'integer') . ", " .
                $ilDB->quote($this->obj_id, 'integer') . ", " .
                $ilDB->quote($a_usr_id, 'integer') . ", " .
                $ilDB->quote(0, 'integer') . ", " .
                $ilDB->quote(0, 'integer') .
                ")";
        }
        $res = $ilDB->manipulate($query);
        return true;
    }
    
    // cognos-blu-patch: begin
    /**
     * Update contact setting
     * @global type $ilDB
     * @param type $a_usr_id
     * @param type $a_contact
     * @return boolean
     */
    public function updateContact($a_usr_id, $a_contact)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $ilDB->manipulate(
            'UPDATE obj_members SET ' .
                'contact = ' . $ilDB->quote($a_contact, 'integer') . ' ' .
                'WHERE obj_id = ' . $ilDB->quote($this->obj_id, 'integer') . ' ' .
                'AND usr_id = ' . $ilDB->quote($a_usr_id, 'integer')
        );
        
        $this->participants_status[$a_usr_id]['contact'] = $a_contact;
        return true;
    }
    
    /**
     * get user ids which are confirgured as contact
     * @return array
     */
    public function getContacts()
    {
        $contacts = array();
        foreach ((array) $this->participants_status as $usr_id => $status) {
            if ($status['contact']) {
                $contacts[] = $usr_id;
            }
        }
        return $contacts;
    }
    
    
    // cognos-blu-patch: end

    /**
     * Update notification status
     *
     * @access public
     * @param int usr_id
     * @param bool passed
     *
     */
    public function updateNotification($a_usr_id, $a_notification)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $this->participants_status[$a_usr_id]['notification'] = (int) $a_notification;

        $query = "SELECT * FROM obj_members " .
        "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
        "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        $res = $ilDB->query($query);
        if ($res->numRows()) {
            $query = "UPDATE obj_members SET " .
                "notification = " . $ilDB->quote((int) $a_notification, 'integer') . " " .
                "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
                "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        } else {
            $query = "INSERT INTO obj_members (notification,obj_id,usr_id,passed,blocked) " .
                "VALUES ( " .
                $ilDB->quote((int) $a_notification, 'integer') . ", " .
                $ilDB->quote($this->obj_id, 'integer') . ", " .
                $ilDB->quote($a_usr_id, 'integer') . ", " .
                $ilDB->quote(0, 'integer') . ", " .
                $ilDB->quote(0, 'integer') .
                ")";
        }
        $res = $ilDB->manipulate($query);
        return true;
    }
    

    
    
    /**
     * Add user to object
     *
     * @access public
     * @param int user id
     * @param int role IL_CRS_ADMIN || IL_CRS_TUTOR || IL_CRS_MEMBER
     *
     */
    public function add($a_usr_id, $a_role)
    {
        global $DIC;

        $rbacadmin = $DIC['rbacadmin'];
        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        if ($this->isAssigned($a_usr_id)) {
            return false;
        }

        switch ($a_role) {
            case IL_CRS_ADMIN:
                $this->admins[] = $a_usr_id;
                break;

            case IL_CRS_TUTOR:
                $this->tutors[] = $a_usr_id;
                break;

            case IL_CRS_MEMBER:
                $this->members[] = $a_usr_id;
                break;

            case IL_GRP_ADMIN:
                $this->admins[] = $a_usr_id;
                break;

            case IL_GRP_MEMBER:
                $this->members[] = $a_usr_id;
                break;

            case IL_LSO_ADMIN:
                $this->admins[] = $a_usr_id;
                break;

            case IL_LSO_MEMBER:
                $this->members[] = $a_usr_id;
                break;

            case IL_SESS_MEMBER:
                $this->members[] = $a_usr_id;
                break;
        }
        
        $this->participants[] = $a_usr_id;
        $rbacadmin->assignUser($this->role_data[$a_role], $a_usr_id);
        
        // Delete subscription request
        $this->deleteSubscriber($a_usr_id);
        
        include_once './Services/Membership/classes/class.ilWaitingList.php';
        ilWaitingList::deleteUserEntry($a_usr_id, $this->obj_id);

        $ilAppEventHandler->raise(
            $this->getComponent(),
            "addParticipant",
            array(
                    'obj_id' => $this->obj_id,
                    'usr_id' => $a_usr_id,
                    'role_id' => $a_role)
        );
        return true;
    }
    

    /**
     * fau: heavySub - Add user to a role with limited members
     * Note: check the result, then call addLimitedSuccess()
     *
     * @access public
     * @param 	int $a_usr_id	user id
     * @param 	int $a_role		role IL_CRS_MEMBER | IL_GRP_MEMBER
     * @param 	?int $a_max		maximum members (null, if no maximum defined)
     * @return  bool 	        user added (true) or not (false) or already assigned (null)
     */
    public function addLimited(int $a_usr_id, int $a_role, ?int $a_max = null) : ?bool
    {
        global $DIC;

        if ($this->isAssigned($a_usr_id)) {
            return null;
        } elseif (!$DIC->rbac()->admin()->assignUserLimitedCust((int) $this->role_data[$a_role], $a_usr_id, $a_max)) {
            return false;
        }
        return true;
    }
    // fau.


    /**
     * fau: heavySub - Notify the success of adding a user to a role with limited members
     *
     * @access public
     * @param 	int $a_usr_id	user id
     * @param 	int $a_role		role IL_CRS_MEMBER | IL_GRP_MEMBER
     */

    public function addLimitedSuccess(int $a_usr_id, int $a_role)
    {
        global $DIC;

        switch ($a_role) {
            case IL_CRS_MEMBER:
            case IL_GRP_MEMBER:
                $this->members[] = $a_usr_id;
                break;
        }
        $this->participants[] = $a_usr_id;

        // Delete subscription request
        $this->deleteSubscriber($a_usr_id);
        ilWaitingList::deleteUserEntry($a_usr_id, $this->obj_id);

        $DIC->event()->raise(
            $this->getComponent(),
            "addParticipant",
            array(
                'obj_id' => $this->obj_id,
                'usr_id' => $a_usr_id,
                'role_id' => $a_role)
        );
    }
    // fau.
    

    /**
     * Delete users
     *
     * @access public
     * @param array user ids
     *
     */
    public function deleteParticipants($a_user_ids)
    {
        foreach ($a_user_ids as $user_id) {
            $this->delete($user_id);
        }
        return true;
    }
    
    /**
     * Add desktop item
     *
     * @access public
     * @param int usr_id
     *
     */
    public function addRecommendation($a_usr_id)
    {
        // deactivated for now, see discussion at
        // https://docu.ilias.de/goto_docu_wiki_wpage_5620_1357.html
        // $this->recommended_content_manager->addObjectRecommendation($a_usr_id, $this->ref_id);
        return true;
    }
    

    
    /**
     * check if notification is enabled
     *
     * @access public
     * @param
     *
     */
    public function isNotificationEnabled($a_usr_id)
    {
        if (isset($this->participants_status[$a_usr_id])) {
            return $this->participants_status[$a_usr_id]['notification'] ? true : false;
        }
        return false;
    }
    
    // cognos-blu-patch: begin
    /**
     * Check if user is contact
     * @param int usr_id
     */
    public function isContact($a_usr_id)
    {
        if (isset($this->participants_status[$a_usr_id])) {
            return (bool) $this->participants_status[$a_usr_id]['contact'];
        }
        return false;
    }
    // cognos-blu-patch: end
    
    
    /**
     * Get role id of auto generated role type
     * @param type $a_role_type
     */
    public function getAutoGeneratedRoleId($a_role_type)
    {
        if (array_key_exists($a_role_type, $this->role_data)) {
            return $this->role_data[$a_role_type];
        }
        
        return 0;
    }
    
    
    /**
     * Read participants
     *
     * @access private
     * @param
     *
     */
    protected function readParticipants()
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];
        $ilObjDataCache = $DIC['ilObjDataCache'];
        $ilLog = $DIC['ilLog'];
        
        $GLOBALS['DIC']['rbacreview']->clearCaches();
        $this->roles = $rbacreview->getRolesOfRoleFolder($this->ref_id, false);

        $users = array();
        $this->participants = array();
        $this->members = $this->admins = $this->tutors = array();

        $additional_roles = [];
        $auto_generated_roles = [];
        foreach ($this->roles as $role_id) {
            $title = $ilObjDataCache->lookupTitle($role_id);
            switch (substr($title, 0, 8)) {
                case 'il_crs_m':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_MEMBER;
                    $this->role_data[IL_CRS_MEMBER] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->members = array_unique(array_merge($assigned, $this->members));
                    $this->role_assignments[$role_id] = $assigned;
                    break;

                case 'il_crs_a':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_ADMIN;
                    $this->role_data[IL_CRS_ADMIN] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->admins = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;
        
                case 'il_crs_t':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_TUTOR;
                    $this->role_data[IL_CRS_TUTOR] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->tutors = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;
                    
                case 'il_grp_a':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_ADMIN;
                    $this->role_data[IL_GRP_ADMIN] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->admins = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;
                    
                case 'il_grp_m':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_MEMBER;
                    $this->role_data[IL_GRP_MEMBER] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->members = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;
                
                case 'il_sess_':
                    $this->role_data[IL_SESS_MEMBER] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->members = $rbacreview->assignedUsers($role_id);
                    break;

                case 'il_lso_m':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_MEMBER;
                    $this->role_data[IL_LSO_MEMBER] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->members = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;

                case 'il_lso_a':
                    $auto_generated_roles[$role_id] = IL_ROLE_POSITION_ADMIN;
                    $this->role_data[IL_LSO_ADMIN] = $role_id;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    $this->admins = $rbacreview->assignedUsers($role_id);
                    $this->role_assignments[$role_id] = $assigned;
                    break;

                default:
                    $additional_roles[$role_id] = $title;
                    $this->participants = array_unique(array_merge($assigned = $rbacreview->assignedUsers($role_id), $this->participants));
                    // fau: limitMemberRoles - don't count others roles as members
                    //$this->members = array_unique(array_merge($assigned, $this->members));
                    // fau.
                    $this->role_assignments[$role_id] = $assigned;
                    break;
            }
        }
        asort($auto_generated_roles);
        asort($additional_roles);
        $this->roles_sorted = $auto_generated_roles + $additional_roles;
    }
    
    /**
     * Read status of participants (blocked, notification, passed)
     *
     * @access private
     * @param
     *
     */
    protected function readParticipantsStatus()
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $query = "SELECT * FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " ";
        $res = $ilDB->query($query);
        $this->participants_status = array();
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $this->participants_status[$row->usr_id]['blocked'] = $row->blocked;
            $this->participants_status[$row->usr_id]['notification'] = $row->notification;
            $this->participants_status[$row->usr_id]['passed'] = $row->passed;
            // cognos-blu-patch: begin
            $this->participants_status[$row->usr_id]['contact'] = $row->contact;
            // cognos-blu-patch: end
        }
    }
    
    /**
     * Check grouping membership
     *
     * @access public
     * @param
     *
     */
    public function isGroupingMember($a_usr_id, $a_field = '')
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];
        $ilObjDataCache = $DIC['ilObjDataCache'];
        $ilDB = $DIC['ilDB'];

        // Used for membership limitations -> check membership by given field
        if ($a_field) {
            include_once './Services/User/classes/class.ilObjUser.php';

            $tmp_user = &ilObjectFactory::getInstanceByObjId($a_usr_id);
            switch ($a_field) {
                case 'login':
                    $and = "AND login = " . $ilDB->quote($tmp_user->getLogin(), 'text') . " ";
                    break;
                case 'email':
                    $and = "AND email = " . $ilDB->quote($tmp_user->getEmail(), 'text') . " ";
                    break;
                case 'matriculation':
                    $and = "AND matriculation = " . $ilDB->quote($tmp_user->getMatriculation(), 'text') . " ";
                    break;

                default:
                    $and = "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " ";
                    break;
            }
            
            if (!$this->getParticipants()) {
                return false;
            }

            $query = "SELECT * FROM usr_data ud " .
                "WHERE " . $ilDB->in('usr_id', $this->getParticipants(), false, 'integer') . " " .
                $and;

            $res = $ilDB->query($query);
            return $res->numRows() ? true : false;
        }
    }

    public static function lookupSubscribers($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $subscribers = array();
        $query = "SELECT usr_id FROM il_subscribers " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
            "ORDER BY sub_time ";

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $subscribers[] = $row->usr_id;
        }
        return $subscribers;
    }
    
    /**
     * get all subscribers
     *
     * @access public
     */
    public function getSubscribers()
    {
        $this->readSubscribers();

        return $this->subscribers;
    }

    
    /**
     * get number of subscribers
     *
     * @access public
     */
    public function getCountSubscribers()
    {
        return count($this->getSubscribers());
    }

    /**
     * get subscriber data
     *
     * @access public
     */
    public function getSubscriberData($a_usr_id)
    {
        return $this->readSubscriberData($a_usr_id);
    }



    /**
     * Assign subscribers
     *
     * @access public
     */
    public function assignSubscribers($a_usr_ids)
    {
        if (!is_array($a_usr_ids) or !count($a_usr_ids)) {
            return false;
        }
        foreach ($a_usr_ids as $id) {
            if (!$this->assignSubscriber($id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Assign subscriber
     *
     * @access public
     */
    public function assignSubscriber($a_usr_id)
    {
        global $DIC;

        $ilErr = $DIC['ilErr'];
        
        $ilErr->setMessage("");
        if (!$this->isSubscriber($a_usr_id)) {
            $ilErr->appendMessage($this->lng->txt("crs_user_notsubscribed"));

            return false;
        }
        if ($this->isAssigned($a_usr_id)) {
            $tmp_obj = ilObjectFactory::getInstanceByObjId($a_usr_id);
            $ilErr->appendMessage($tmp_obj->getLogin() . ": " . $this->lng->txt("crs_user_already_assigned"));

            return false;
        }

        if (!$tmp_obj = &ilObjectFactory::getInstanceByObjId($a_usr_id)) {
            $ilErr->appendMessage($this->lng->txt("crs_user_not_exists"));

            return false;
        }

        // TODO: must be group or course member role
        if ($this instanceof ilCourseParticipants) {
            $this->add($tmp_obj->getId(), IL_CRS_MEMBER);
        }
        if ($this instanceof ilGroupParticipants) {
            $this->add($tmp_obj->getId(), IL_GRP_MEMBER);
        }
        if ($this instanceof ilLearningSequenceParticipants) {
            $this->add($tmp_obj->getId(), IL_LSO_MEMBER);
        }
        if ($this instanceof ilSessionParticipants) {
            $this->register($tmp_obj->getId());
        }
        $this->deleteSubscriber($a_usr_id);
        return true;
    }

    /**
     * Assign subscriber
     *
     * @access public
     */
    public function autoFillSubscribers()
    {
        $this->readSubscribers();

        $counter = 0;
        foreach ($this->subscribers as $subscriber) {
            if (!$this->assignSubscriber($subscriber)) {
                continue;
            } else {
                // TODO: notification
                #$this->sendNotification($this->NOTIFY_ACCEPT_SUBSCRIBER,$subscriber);
            }
            ++$counter;
        }

        return $counter;
    }

    /**
     * Add subscriber
     *
     * @access public
     */
    public function addSubscriber($a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "INSERT INTO il_subscribers (usr_id,obj_id,subject,sub_time) " .
            " VALUES (" .
            $ilDB->quote($a_usr_id, 'integer') . "," .
            $ilDB->quote($this->obj_id, 'integer') . ", " .
            $ilDB->quote('', 'text') . ", " .
            $ilDB->quote(time(), 'integer') .
            ")";
        $res = $ilDB->manipulate($query);

        return true;
    }


    /**
     * Update subscription time
     *
     * @access public
     */
    public function updateSubscriptionTime($a_usr_id, $a_subtime)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "UPDATE il_subscribers " .
            "SET sub_time = " . $ilDB->quote($a_subtime, 'integer') . " " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);

        return true;
    }
    
    /**
     * update subject
     *
     * @access public
     * @param
     * @return
     */
    public function updateSubject($a_usr_id, $a_subject)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $query = "UPDATE il_subscribers " .
            "SET subject = " . $ilDB->quote($a_subject, 'text') . " " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);
        return true;
    }

    
    /**
     * Delete subsciber
     *
     * @access public
     */
    public function deleteSubscriber($a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "DELETE FROM il_subscribers " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " ";
        $res = $ilDB->manipulate($query);

        return true;
    }

    
    /**
     * Delete subscibers
     *
     * @access public
     */
    public function deleteSubscribers($a_usr_ids)
    {
        global $DIC;

        $ilErr = $DIC['ilErr'];
        $ilDB = $DIC['ilDB'];
        
        if (!is_array($a_usr_ids) or !count($a_usr_ids)) {
            $ilErr->setMessage('');
            $ilErr->appendMessage($this->lng->txt("no_usr_ids_given"));

            return false;
        }
        $query = "DELETE FROM il_subscribers " .
            "WHERE " . $ilDB->in('usr_id', (array) $a_usr_ids, false, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer');
        $res = $ilDB->query($query);
        return true;
    }
    
    
    /**
     * check if is subscriber
     *
     * @access public
     */
    public function isSubscriber($a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM il_subscribers " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($this->obj_id, 'integer') . "";

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            return true;
        }
        return false;
    }

    /**
     * check if user is subscriber
     *
     * @access public
     * @static
     */
    public static function _isSubscriber($a_obj_id, $a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM il_subscribers " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($a_obj_id, 'integer') . "";

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            return true;
        }
        return false;
    }
    
    /**
     * read subscribers
     *
     * @access protected
     */
    protected function readSubscribers()
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $this->subscribers = array();

        $query = "SELECT usr_id FROM il_subscribers " .
            "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
            "ORDER BY sub_time ";

        $res = $this->ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            // DELETE SUBSCRIPTION IF USER HAS BEEN DELETED
            if (!ilObjectFactory::getInstanceByObjId($row->usr_id, false)) {
                $this->deleteSubscriber($row->usr_id);
            }
            $this->subscribers[] = $row->usr_id;
        }
        return true;
    }

    // fau: courseUdf - new function sendExternalNotification

    /**
     * @param ilObjCourse|ilObjGroup $a_object
     * @param ilObjUser	$a_user
     * @param bool	$a_changed	registration is changed, not added
     */
    public function sendExternalNotifications($a_object, $a_user, $a_changed = false)
    {
        global $ilSetting;

        $user_data = ilCourseUserData::getFieldsWithData($a_object->getId(), $a_user->getId());
        $notifications = array();

        /** @var ilCourseDefinedFieldDefinition $field */
        foreach ($user_data as $data) {
            $field = $data['field'];
            $value = $data['value'];
            if ($field->getType() == IL_CDF_TYPE_EMAIL && $field->getEmailAuto() && ilUtil::is_email($value)) {
                $notifications[$value] = $field->getEmailText();
            }
        }

        if (empty($notifications)) {
            return;
        }

        // prepare common data
        $sender = new ilMailMimeSenderUserById($ilSetting, $a_user->getId());

        $sender_address = $sender->getReplyToAddress();
        $cc_address = '';
        foreach ($this->getNotificationRecipients() as $admin_id) {
            $address = ilObjUser::_lookupEmail($admin_id);
            if (!empty($address)) {
                $cc_address = $address;
                break;
            }
        }
        if (!empty($cc_address)) {
            $reply_link = '<a href="mailto:' . $sender_address . '?cc=' . $cc_address . '">' . $sender_address . ', ' . $cc_address . '</a>';
        } else {
            $reply_link = '<a href="mailto:' . $sender_address . '">' . $sender_address . '</a>';
        }


        if ($a_changed) {
            $subject = sprintf($this->lng->txt('mem_external_notification_subject_changed'), $a_user->getFullname(), $a_object->getTitle());
        } else {
            $subject = sprintf($this->lng->txt('mem_external_notification_subject'), $a_user->getFullname(), $a_object->getTitle());
        }

        $sep = ":\n";
        $list = array();
        $list[] = $this->lng->txt('user') . $sep . $a_user->getFullname();
        $list[] = $this->lng->txt('email') . $sep . $a_user->getEmail();
        $list[] = $this->lng->txt('title') . $sep . $a_object->getTitle();
        if ($a_object->getType() == 'crs' && !empty($a_object->getSyllabus())) {
            $list[] = $this->lng->txt('crs_syllabus') . $sep . $a_object->getSyllabus();
        }

        foreach ($user_data as $data) {
            /** @var ilCourseDefinedFieldDefinition $field */
            $field = $data['field'];
            if (!empty($data['value'])) {
                $list[] = $field->getName() . $sep . $data['value'];
            }
        }

        $sub_text = implode("\n\n", $list);


        // send the notifications

        foreach ($notifications as $to_address => $text) {
            $body = str_replace('[reply-to]', $reply_link, $text) . "\n\n" . $sub_text;

            $mmail = new ilMimeMail();
            $mmail->To($to_address);
            $mmail->From($sender);
            $mmail->Subject($subject);
            $mmail->Body(nl2br($body));
            $mmail->Send();
        }
    }
    // fau.

    /**
     * read subscribers
     *
     * @access protected
     */
    protected function readSubscriberData($a_usr_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM il_subscribers " .
            "WHERE obj_id = " . $ilDB->quote($this->obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer') . "";

        $res = $this->ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $data["time"] = $row->sub_time;
            $data["usr_id"] = $row->usr_id;
            $data['subject'] = $row->subject;
        }
        return $data ? $data : array();
    }

    public static function lookupSubscribersData($a_obj_id)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = 'SELECT * FROM il_subscribers ' .
            'WHERE obj_id = ' . $ilDB->quote($a_obj_id, 'integer');
        $res = $ilDB->query($query);

        $data = array();
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $data[$row->usr_id]['time'] = $row->sub_time;
            $data[$row->usr_id]['usr_id'] = $row->usr_id;
            $data[$row->usr_id]['subject'] = $row->subject;
        }
        return $data;
    }

    /**
     * Get all support contacts for a user
     *
     * @param int $a_usr_id usr_id
     * @param string $a_type crs or grp
     * @return array array of contacts (keys are usr_id and obj_id)
     */
    public static function _getAllSupportContactsOfUser($a_usr_id, $a_type)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        // todo: join the two queries or alternatively reuse _getMembershipByType
        // for the first part

        // this will also dismiss local roles!
        $j2 = "JOIN object_data obd2 ON (ua.rol_id = obd2.obj_id) ";
        $a2 = "AND obd2.title LIKE 'il_" . $a_type . "_mem%' ";

        // #14290 - no role folder anymore
        $query = "SELECT DISTINCT obd.obj_id,obr.ref_id FROM rbac_ua ua " .
            "JOIN rbac_fa fa ON ua.rol_id = fa.rol_id " .
            "JOIN object_reference obr ON fa.parent = obr.ref_id " .
            "JOIN object_data obd ON obr.obj_id = obd.obj_id " .
            $j2 .
            "WHERE obd.type = " . $ilDB->quote($a_type, 'text') . " " .
            "AND fa.assign = 'y' " .
            "AND ua.usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            $a2;

        $res = $ilDB->query($query);
        $obj_ids = array();
        while ($row = $ilDB->fetchObject($res)) {
            $obj_ids[] = $row->obj_id;
        }

        $set = $ilDB->query("SELECT obj_id, usr_id FROM obj_members " .
            " WHERE " . $ilDB->in("obj_id", $obj_ids, false, "integer") .
            " AND contact = " . $ilDB->quote(1, "integer"));
        $res = array();
        while ($rec = $ilDB->fetchAssoc($set)) {
            $res[] = $rec;
        }

        return $res;
    }

    /**
     * Set role order position
     * @param  int $a_user_id
     * @return string
     */
    public function setRoleOrderPosition($a_user_id)
    {
        $counter = 0;
        $sortable_assignments = '9999999999';
        foreach ($this->roles_sorted as $role_id => $trash) {
            if (in_array($a_user_id, (array) $this->role_assignments[$role_id])) {
                $sortable_assignments = substr_replace($sortable_assignments, '1', $counter, 1);
            }
            ++$counter;
        }
        return $sortable_assignments;
    }
}
