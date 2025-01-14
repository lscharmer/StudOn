<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Membership/classes/class.ilMembershipRegistrationSettings.php';

/**
* Registration settings
*
* @author Stefan Meyer <meyer@leifos.com>
*
* @version $Id$
*
* @ingroup ServicesMembership
*/
abstract class ilMembershipRegistrationSettingsGUI
{
    private $object = null;
    private $gui_object = null;
    private $options = array();
    
    /**
     * Constructor
     * @param ilObjectGUI $gui_object
     * @param ilObject $object
     */
    public function __construct(ilObjectGUI $gui_object, ilObject $object, $a_options)
    {
        $this->gui_object = $gui_object;
        $this->object = $object;
        $this->options = $a_options;
    }
    
    /**
     * Set form values
     */
    abstract public function setFormValues(ilPropertyFormGUI $form);
    
    /**
     * Get current object
     * @return ilObject
     */
    public function getCurrentObject()
    {
        return $this->object;
    }
    
    /**
     * Get gui object
     * @return ilObjectGUI
     */
    public function getCurrentGUI()
    {
        return $this->gui_object;
    }
    
    /**
     * Get options
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * Add membership form elements
     * @param ilPropertyFormGUI $form
     */
    final public function addMembershipFormElements(ilPropertyFormGUI $form, $a_parent_post = '')
    {
        // Registration type
        $reg_type = new ilRadioGroupInputGUI($this->txt('reg_type'), 'registration_type');
        //$reg_type->setValue($this->object->getRegistrationType());

        // fau: objectSub - add option for reference to subscription object
        global $lng;
        if (in_array(ilMembershipRegistrationSettings::TYPE_OBJECT, $this->getOptions())) {
            require_once('Services/Form/classes/class.ilRepositorySelectorInputGUI.php');
            $opt_obj = new ilRadioOption($lng->txt('sub_separate_object'), ilMembershipRegistrationSettings::TYPE_OBJECT);
            $opt_obj->setInfo($lng->txt('sub_separate_object_info'));
            $rep_sel = new ilRepositorySelectorInputGUI($lng->txt('sub_subscription_object'), 'registration_ref_id');
            $rep_sel->setHeaderMessage($lng->txt('sub_separate_object_info'));
            $rep_sel->setClickableTypes(array('xcos'));
            $rep_sel->setRequired(true);
            $rep_sel->setParent($form);
            $opt_obj->addSubItem($rep_sel);
            $reg_type->addOption($opt_obj);

            if ($ref_id = $this->object->getRegistrationRefId()) {
                $rep_sel->setValue($ref_id);
                require_once('Services/Locator/classes/class.ilLocatorGUI.php');
                $locator = new ilLocatorGUI();
                $locator->setTextOnly(true);
                $locator->addContextItems($ref_id);
                $rep_loc = new ilNonEditableValueGUI();
                $rep_loc->setValue($locator->getHTML());
                $opt_obj->addSubItem($rep_loc);
            }
        }
        // fau.

        if (in_array(ilMembershipRegistrationSettings::TYPE_DIRECT, $this->getOptions())) {
            $opt_dir = new ilRadioOption($this->txt('reg_direct'), ilMembershipRegistrationSettings::TYPE_DIRECT);
            $opt_dir->setInfo($this->txt('reg_direct_info'));
            $reg_type->addOption($opt_dir);

            // cannot participate
            $cannot_participate = new ilCheckboxInputGUI(
                $this->txt('reg_cannot_participate'),
                'show_cannot_participate_direct'
            );
            $cannot_participate->setInfo($this->txt('reg_cannot_participate_info'));
            $cannot_participate->setValue(1);
            $opt_dir->addSubItem($cannot_participate);
        }
        if (in_array(ilMembershipRegistrationSettings::TYPE_PASSWORD, $this->getOptions())) {
            $opt_pass = new ilRadioOption($this->txt('reg_pass'), ilMembershipRegistrationSettings::TYPE_PASSWORD);
            $pass = new ilTextInputGUI($GLOBALS['DIC']['lng']->txt("password"), 'password');
            $pass->setInfo($this->txt('reg_password_info'));
            #$pass->setValue($this->object->getPassword());
            $pass->setSize(10);
            $pass->setMaxLength(32);
            $opt_pass->addSubItem($pass);
            $reg_type->addOption($opt_pass);
        }

        if (in_array(ilMembershipRegistrationSettings::TYPE_REQUEST, $this->getOptions())) {
            $opt_req = new ilRadioOption($this->txt('reg_request'), ilMembershipRegistrationSettings::TYPE_REQUEST, $this->txt('reg_request_info'));
            $reg_type->addOption($opt_req);

            // cannot participate
            $cannot_participate = new ilCheckboxInputGUI(
                $this->txt('reg_cannot_participate'),
                'show_cannot_participate_request'
            );
            $cannot_participate->setInfo($this->txt('reg_cannot_participate_info'));
            $cannot_participate->setValue(1);
            $opt_req->addSubItem($cannot_participate);

        }
        if (in_array(ilMembershipRegistrationSettings::TYPE_TUTOR, $this->getOptions())) {
            $opt_tutor = new ilRadioOption(
                $this->txt('reg_tutor'),
                ilMembershipRegistrationSettings::TYPE_TUTOR,
                $this->txt('reg_tutor_info')
            );
            $reg_type->addOption($opt_tutor);
        }
        if (in_array(ilMembershipRegistrationSettings::TYPE_NONE, $this->getOptions())) {
            $opt_deact = new ilRadioOption($this->txt('reg_disabled'), ilMembershipRegistrationSettings::TYPE_NONE, $this->txt('reg_disabled_info'));
            $reg_type->addOption($opt_deact);
        }
        
        // Add to form
        $form->addItem($reg_type);
        
        if (in_array(ilMembershipRegistrationSettings::REGISTRATION_LIMITED_USERS, $this->getOptions())) {
            // max member
            $lim = new ilCheckboxInputGUI($this->txt('reg_max_members_short'), 'registration_membership_limited');
            $lim->setValue(1);
            #$lim->setOptionTitle($this->lng->txt('reg_grp_max_members'));
            #$lim->setChecked($this->object->isMembershipLimited());

            /* JF, 2015-08-31 - only used in sessions which cannot support min members (yet)
            $min = new ilTextInputGUI($this->txt('reg_min_members'),'registration_min_members');
            $min->setSize(3);
            $min->setMaxLength(4);
            $min->setInfo($this->txt('reg_min_members_info'));
            $lim->addSubItem($min);
            */
            
            $max = new ilTextInputGUI($this->txt('reg_max_members'), 'registration_max_members');
            #$max->setValue($this->object->getMaxMembers() ? $this->object->getMaxMembers() : '');
            //$max->setTitle($this->lng->txt('members'));
            $max->setSize(3);
            $max->setMaxLength(4);
            $max->setInfo($this->txt('reg_max_members_info'));
            $lim->addSubItem($max);

            /*
            $wait = new ilCheckboxInputGUI($this->txt('reg_waiting_list'),'waiting_list');
            $wait->setValue(1);
            //$wait->setOptionTitle($this->lng->txt('grp_waiting_list'));
            $wait->setInfo($this->txt('reg_waiting_list_info'));
            #$wait->setChecked($this->object->isWaitingListEnabled() ? true : false);
            $lim->addSubItem($wait);
            */
            
            $wait = new ilRadioGroupInputGUI($this->txt('reg_waiting_list'), 'waiting_list');
            
            $option = new ilRadioOption($this->txt('reg_waiting_list_none'), 0);
            $wait->addOption($option);
            
            $option = new ilRadioOption($this->txt('reg_waiting_list_no_autofill'), 1);
            $option->setInfo($this->txt('reg_waiting_list_no_autofill_info'));
            $wait->addOption($option);
            
            $option = new ilRadioOption($this->txt('reg_waiting_list_autofill'), 2);
            $option->setInfo($this->txt('reg_waiting_list_autofill_info'));
            $wait->addOption($option);
            
            $lim->addSubItem($wait);
                        
            $form->addItem($lim);
        }

        $notificationCheckbox = new ilCheckboxInputGUI($this->txt('registration_notification'), 'registration_notification');
        $notificationCheckbox->setInfo($this->txt('registration_notification_info'));

        $notificationOption = new ilRadioGroupInputGUI($this->txt('notification_option'), 'notification_option');
        $notificationOption->setRequired(true);

        $inheritOption = new ilRadioOption($this->txt(ilSessionConstants::NOTIFICATION_INHERIT_OPTION), ilSessionConstants::NOTIFICATION_INHERIT_OPTION);
        $inheritOption->setInfo($this->txt('notification_option_inherit_info'));
        $notificationOption->addOption($inheritOption);

        $manualOption = new ilRadioOption($this->txt(ilSessionConstants::NOTIFICATION_MANUAL_OPTION), ilSessionConstants::NOTIFICATION_MANUAL_OPTION);
        $manualOption->setInfo($this->txt('notification_option_manual_info'));
        $notificationOption->addOption($manualOption);

        $notificationCheckbox->addSubItem($notificationOption);
        $form->addItem($notificationCheckbox);

        $this->setFormValues($form);
    }
    
    /**
     * Translate type specific
     */
    protected function txt($a_lang_key)
    {
        $prefix = $this->getCurrentObject()->getType();
        return $GLOBALS['DIC']['lng']->txt($prefix . '_' . $a_lang_key);
    }
}
