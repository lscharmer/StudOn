<?php
/*
        +-----------------------------------------------------------------------------+
        | ILIAS open source                                                           |
        +-----------------------------------------------------------------------------+
        | Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
        |                                                                             |
        | This program is free software; you can redistribute it and/or               |
        | modify it under the terms of the GNU General Public License                 |
        | as published by the Free Software Foundation; either version 2              |
        | of the License, or (at your option) any later version.                      |
        |                                                                             |
        | This program is distributed in the hope that it will be useful,             |
        | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
        | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
        | GNU General Public License for more details.                                |
        |                                                                             |
        | You should have received a copy of the GNU General Public License           |
        | along with this program; if not, write to the Free Software                 |
        | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
        +-----------------------------------------------------------------------------+
*/

include_once './Services/Object/classes/class.ilObjectAccess.php';

/**
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ModulesSession
*/

class ilObjSessionAccess extends ilObjectAccess
{
    protected static $registrations = null;
    protected static $registered = null;

    /**
     * @var ilBookingReservationDBRepository
     */
    protected static $booking_repo = null;

    /**
     * get list of command/permission combinations
     *
     * @access public
     * @return array
     * @static
     */
    public static function _getCommands()
    {
        $commands = array(
            array("permission" => "read", "cmd" => "infoScreen", "lang_var" => "info_short", "default" => true),
            array("permission" => "read", "cmd" => "register", "lang_var" => "join_session"),
            array("permission" => "read", "cmd" => "unregister", "lang_var" => "event_unregister"),
            array("permission" => "write", "cmd" => "edit", "lang_var" => "settings"),
            array("permission" => "manage_materials", "cmd" => "materials", "lang_var" => "crs_objective_add_mat"),
            array('permission' => 'manage_members', 'cmd' => 'members', 'lang_var' => 'event_edit_members')
        );
        
        return $commands;
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
        
        if (!$a_user_id) {
            $a_user_id = $ilUser->getId();
        }
        
        switch ($a_cmd) {
            case 'register':

                if (!self::_lookupRegistration($a_obj_id)) {
                    return false;
                }
                if ($ilUser->isAnonymous()) {
                    return false;
                }
                if (self::_lookupRegistered($a_user_id, $a_obj_id)) {
                    return false;
                }
                if (\ilSessionParticipants::_isSubscriber($a_obj_id, $a_user_id)) {
                    return false;
                }
                include_once './Modules/Session/classes/class.ilSessionWaitingList.php';
                if (ilSessionWaitingList::_isOnList($a_user_id, $a_obj_id)) {
                    return false;
                }
                if ($this->isRegistrationLimitExceeded($a_ref_id, $a_obj_id)) {
                    return false;
                }
                break;
                
            case 'unregister':
                if (self::_lookupRegistration($a_obj_id) && $a_user_id != ANONYMOUS_USER_ID) {
                    return self::_lookupRegistered($a_user_id, $a_obj_id);
                }
                return false;
        }
        return true;
    }

    public function isRegistrationLimitExceeded(int $ref_id, int $obj_id) : bool
    {
        $session_data = new ilObjSession($obj_id, false);
        if (!$session_data->isRegistrationUserLimitEnabled()) {
            return false;
        }
        $part = ilSessionParticipants::getInstance($ref_id);
        if ($part->getCountMembers() >= $session_data->getRegistrationMaxUsers()) {
            return true;
        }
        return false;
    }
    
    
    /**
    * check whether goto script will succeed
    */
    public static function _checkGoto($a_target)
    {
        global $DIC;

        $ilAccess = $DIC['ilAccess'];
        
        $t_arr = explode("_", $a_target);

        if ($t_arr[0] != "sess" || ((int) $t_arr[1]) <= 0) {
            return false;
        }

        if ($ilAccess->checkAccess("read", "", $t_arr[1]) ||
            $ilAccess->checkAccess("visible", "", $t_arr[1])) {
            return true;
        }
        return false;
    }

    /**
     * lookup registrations
     *
     * @access public
     * @param
     * @return
     * @static
     */
    public static function _lookupRegistration($a_obj_id)
    {
        if (!is_null(self::$registrations)) {
            return self::$registrations[$a_obj_id];
        }
        
        global $DIC;

        $ilDB = $DIC['ilDB'];
        
        $query = "SELECT registration,obj_id FROM event ";
        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            self::$registrations[$row->obj_id] = (bool) $row->registration;
        }
        return self::$registrations[$a_obj_id];
    }

    /**
     * lookup if user has registered
     *
     * @access public
     * @param int usr_id
     * @param int obj_id
     * @return
     * @static
     */
    public static function _lookupRegistered($a_usr_id, $a_obj_id)
    {
        if (isset(self::$registered[$a_usr_id])) {
            return (bool) self::$registered[$a_usr_id][$a_obj_id];
        }
        
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $ilUser = $DIC['ilUser'];

        // fau: fixSessionRegistered - take the user parameter instead of ilUser
        $a_usr_id = $a_usr_id ? $a_usr_id : $ilUser->getId();
        $query = "SELECT event_id, registered FROM event_participants WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        // fau.
        $res = $ilDB->query($query);
        self::$registered[$a_usr_id] = array();
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            self::$registered[$a_usr_id][$row->event_id] = (bool) $row->registered;
        }
        return (bool) self::$registered[$a_usr_id][$a_obj_id];
    }

    /**
     * Preload data
     *
     * @param array $a_obj_ids array of object ids
     */
    public static function _preloadData($a_obj_ids, $a_ref_ids)
    {
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
}
