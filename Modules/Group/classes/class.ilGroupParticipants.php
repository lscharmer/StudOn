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

include_once('./Services/Membership/classes/class.ilParticipants.php');

/**
*
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ModulesGroup
*/


class ilGroupParticipants extends ilParticipants
{
    const COMPONENT_NAME = 'Modules/Group';
    
    protected static $instances = array();

    /**
     * Constructor
     *
     * @access protected
     * @param int obj_id of container
     */
    public function __construct($a_obj_id)
    {
        // ref based constructor
        $refs = ilObject::_getAllReferences($a_obj_id);
        parent::__construct(self::COMPONENT_NAME, array_pop($refs));
    }
    
    /**
     * Get singleton instance
     *
     * @access public
     * @static
     *
     * @param int obj_id
     * @return ilGroupParticipants
     */
    public static function _getInstanceByObjId($a_obj_id)
    {
        if (isset(self::$instances[$a_obj_id]) and self::$instances[$a_obj_id]) {
            return self::$instances[$a_obj_id];
        }
        return self::$instances[$a_obj_id] = new ilGroupParticipants($a_obj_id);
    }
    
    /**
     * Get member roles (not auto generated)
     * @param int $a_ref_id
     */
    public static function getMemberRoles($a_ref_id)
    {
        global $DIC;

        $rbacreview = $DIC['rbacreview'];

        $lrol = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);

        $roles = array();
        foreach ($lrol as $role) {
            $title = ilObject::_lookupTitle($role);
            switch (substr($title, 0, 8)) {
                case 'il_grp_a':
                case 'il_grp_m':
                    continue 2;

                default:
                    $roles[$role] = $role;
            }
        }
        return $roles;
    }
    
    /**
     * Add user to role
     * @param int $a_usr_id
     * @param int $a_role
     * @return boolean
     */
    public function add($a_usr_id, $a_role)
    {
        if (parent::add($a_usr_id, $a_role)) {
            $this->addRecommendation($a_usr_id);
            return true;
        }
        return false;
    }
    
    public function addSubscriber($a_usr_id)
    {
        global $DIC;

        $ilAppEventHandler = $DIC['ilAppEventHandler'];
        $ilLog = $DIC['ilLog'];
        
        parent::addSubscriber($a_usr_id);

        $GLOBALS['DIC']->logger()->grp()->info('Raise new event: Modules/Group addSubscriber.');
        $ilAppEventHandler->raise(
            "Modules/Group",
            'addSubscriber',
            array(
                    'obj_id' => $this->getObjId(),
                    'usr_id' => $a_usr_id
                )
            );
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
        $ilObjDataCache = $DIC['ilObjDataCache'];
        $ilDB = $DIC['ilDB'];
        $ilLog = $DIC['ilLog'];

        $local_roles = $rbacreview->getRolesOfRoleFolder($a_ref_id, false);
        return $rbacreview->isAssignedToAtLeastOneGivenRole($a_usr_id, $local_roles);
    }
    
    /**
     * Send notification mail
     * @param int $a_type
     * @param int $a_usr_id
     * @return
     */
    public function sendNotification($a_type, $a_usr_id, $a_force_sending_mail = false)
    {
        include_once './Modules/Group/classes/class.ilGroupMembershipMailNotification.php';
        $mail = new ilGroupMembershipMailNotification();
        $mail->forceSendingMail($a_force_sending_mail);
        
        switch ($a_type) {
            case ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER:

                $mail->setType(ilGroupMembershipMailNotification::TYPE_ADMISSION_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;
            
            case ilGroupMembershipMailNotification::TYPE_DISMISS_MEMBER:

                $mail->setType(ilGroupMembershipMailNotification::TYPE_DISMISS_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_NOTIFICATION_REGISTRATION:
                
                $mail->setType(ilGroupMembershipMailNotification::TYPE_NOTIFICATION_REGISTRATION);
                $mail->setAdditionalInformation(array('usr_id' => $a_usr_id));
                $mail->setRefId($this->ref_id);
                $mail->setRecipients($this->getNotificationRecipients());
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_UNSUBSCRIBE_MEMBER:
                
                $mail->setType(ilGroupMembershipMailNotification::TYPE_UNSUBSCRIBE_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_NOTIFICATION_UNSUBSCRIBE:
                    
                $mail->setType(ilGroupMembershipMailNotification::TYPE_NOTIFICATION_UNSUBSCRIBE);
                $mail->setAdditionalInformation(array('usr_id' => $a_usr_id));
                $mail->setRefId($this->ref_id);
                $mail->setRecipients($this->getNotificationRecipients());
                $mail->send();
                break;

            case ilGroupMembershipMailNotification::TYPE_SUBSCRIBE_MEMBER:
                
                $mail->setType(ilGroupMembershipMailNotification::TYPE_SUBSCRIBE_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_NOTIFICATION_REGISTRATION_REQUEST:

                $mail->setType(ilGroupMembershipMailNotification::TYPE_NOTIFICATION_REGISTRATION_REQUEST);
                $mail->setAdditionalInformation(array('usr_id' => $a_usr_id));
                $mail->setRefId($this->ref_id);
                $mail->setRecipients($this->getNotificationRecipients());
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_REFUSED_SUBSCRIPTION_MEMBER:

                $mail->setType(ilGroupMembershipMailNotification::TYPE_REFUSED_SUBSCRIPTION_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;
                
            case ilGroupMembershipMailNotification::TYPE_ACCEPTED_SUBSCRIPTION_MEMBER:
                
                $mail->setType(ilGroupMembershipMailNotification::TYPE_ACCEPTED_SUBSCRIPTION_MEMBER);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;

// fau: fairSub - deprecated case, fallback to specific function
            case ilGroupMembershipMailNotification::TYPE_WAITING_LIST_MEMBER:
                $this->sendAddedToWaitingList($a_usr_id);
                break;
// fau.
                
            case ilGroupMembershipMailNotification::TYPE_STATUS_CHANGED:

                $mail->setType(ilGroupMembershipMailNotification::TYPE_STATUS_CHANGED);
                $mail->setRefId($this->ref_id);
                $mail->setRecipients(array($a_usr_id));
                $mail->send();
                break;


        }
        return true;
    }

    // fau: fairSub - new function sendAddedToWaitingList()
    /**
     * Send notification to user about being added to the waiting list
     * @param int			$a_usr_id
     * @param ilWaitingList	$a_waiting_list
     * @return bool
     */
    public function sendAddedToWaitingList($a_usr_id, $a_waiting_list = null)
    {
        $mail = new ilGroupMembershipMailNotification();
        $mail->setType(ilGroupMembershipMailNotification::TYPE_WAITING_LIST_MEMBER);
        $mail->setRefId($this->ref_id);
        $mail->setWaitingList($a_waiting_list);
        $mail->setRecipients(array($a_usr_id));
        $mail->send();
        return true;
    }
    // fau.

    // fau: fairSub - new function sendSubscriptionRequestToAdmins()
    public function sendSubscriptionRequestToAdmins($a_usr_id)
    {
        $mail = new ilGroupMembershipMailNotification();
        $mail->setType(ilGroupMembershipMailNotification::TYPE_NOTIFICATION_REGISTRATION_REQUEST);
        $mail->setAdditionalInformation(array('usr_id' => $a_usr_id));
        $mail->setRefId($this->ref_id);
        $mail->setRecipients($this->getNotificationRecipients());
        $mail->send();
        return true;
    }
    // fau.
}
