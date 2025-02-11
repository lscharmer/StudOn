<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

include_once './Services/Membership/classes/class.ilParticipantsTableGUI.php';
include_once './Services/Tracking/classes/class.ilLPStatus.php';

/**
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ModulesGroup
*/
class ilGroupParticipantsTableGUI extends ilParticipantTableGUI
{
    protected $show_learning_progress = false;
    
    protected $current_filter = array();

    /**
     * Constructor
     *
     * @access public
     * @param
     * @return
     */
    public function __construct(
        $a_parent_obj,
        ilObject $rep_object,
        $show_learning_progress = false
    ) {
        global $DIC;

        $lng = $DIC['lng'];
        $ilCtrl = $DIC['ilCtrl'];

        $this->show_learning_progress = $show_learning_progress;

        $this->lng = $lng;
        $this->lng->loadLanguageModule('grp');
        $this->lng->loadLanguageModule('trac');
        $this->lng->loadLanguageModule('rbac');
        $this->lng->loadLanguageModule('mmbr');

        $this->ctrl = $ilCtrl;
        
        $this->rep_object = $rep_object;

        include_once('./Services/PrivacySecurity/classes/class.ilPrivacySettings.php');
        $this->privacy = ilPrivacySettings::_getInstance();
        
        include_once './Services/Membership/classes/class.ilParticipants.php';
        $this->participants = ilParticipants::getInstanceByObjId($this->getRepositoryObject()->getId());

        
        $this->setPrefix('participants');

        $this->setId('grp_' . $this->getRepositoryObject()->getId());
        parent::__construct($a_parent_obj, 'participants');

        $this->initSettings();

        $this->setFormName('participants');

        $this->addColumn('', 'f', "1", true);
        $this->addColumn($this->lng->txt('name'), 'lastname', '20%');

        $all_cols = $this->getSelectableColumns();
        foreach ($this->getSelectedColumns() as $col) {
            $this->addColumn($all_cols[$col]['txt'], $col);
        }

        if ($this->show_learning_progress) {
            $this->addColumn($this->lng->txt('learning_progress'), 'progress');
        }

        if ($this->privacy->enabledGroupAccessTimes()) {
            $this->addColumn($this->lng->txt('last_access'), 'access_time_unix');
        }
        $this->addColumn($this->lng->txt('grp_mem_contacts'), 'contact');
        $this->addColumn($this->lng->txt('grp_notification'), 'notification');

        $this->addColumn($this->lng->txt('actions'), 'optional', '', false, 'ilMembershipRowActionsHeader');
        $this->setDefaultOrderField('roles');

        $this->setRowTemplate("tpl.show_participants_row.html", "Modules/Group");

        $this->setShowRowsSelector(true);

        $this->enable('sort');
        $this->enable('header');
        $this->enable('numinfo');
        $this->enable('select_all');
        
        $this->initFilter();
        
        $this->addMultiCommand('editParticipants', $this->lng->txt('edit'));
        $this->addMultiCommand('confirmDeleteParticipants', $this->lng->txt('remove'));
        $this->addMultiCommand('sendMailToSelectedUsers', $this->lng->txt('mmbr_btn_mail_selected_users'));
        $this->lng->loadLanguageModule('user');
        $this->addMultiCommand('addToClipboard', $this->lng->txt('clipboard_add_btn'));
        
        $this->setSelectAllCheckbox('participants', true);
        $this->addCommandButton('updateParticipantsStatus', $this->lng->txt('save'));
    }

    /**
     * fill row
     *
     * @access public
     * @param
     * @return
     */
    public function fillRow($a_set)
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        $ilAccess = $DIC['ilAccess'];
        
        $this->tpl->setVariable('VAL_ID', $a_set['usr_id']);
        $this->tpl->setVariable('VAL_NAME', $a_set['lastname'] . ', ' . $a_set['firstname']);
        if (!$ilAccess->checkAccessOfUser($a_set['usr_id'], 'read', '', $this->getRepositoryObject()->getRefId()) and
            is_array($info = $ilAccess->getInfo())) {
            $this->tpl->setCurrentBlock('access_warning');
            $this->tpl->setVariable('PARENT_ACCESS', $info[0]['text']);
            $this->tpl->parseCurrentBlock();
        }

        if (!ilObjUser::_lookupActive($a_set['usr_id'])) {
            $this->tpl->setCurrentBlock('access_warning');
            $this->tpl->setVariable('PARENT_ACCESS', $this->lng->txt('usr_account_inactive'));
            $this->tpl->parseCurrentBlock();
        }

        
        foreach ($this->getSelectedColumns() as $field) {
            switch ($field) {
                case 'gender':
                    $a_set['gender'] = $a_set['gender'] ? $this->lng->txt('gender_' . $a_set['gender']) : '';
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', $a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;
                    
                case 'birthday':
                    $a_set['birthday'] = $a_set['birthday'] ? ilDatePresentation::formatDate(new ilDate($a_set['birthday'], IL_CAL_DATE)) : $this->lng->txt('no_date');
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', $a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;

                case 'consultation_hour':
                    $this->tpl->setCurrentBlock('custom_fields');
                    $dts = array();
                    foreach ((array) $a_set['consultation_hours'] as $ch) {
                        $tmp = ilDatePresentation::formatPeriod(
                            new ilDateTime($ch['dt'], IL_CAL_UNIX),
                            new ilDateTime($ch['dtend'], IL_CAL_UNIX)
                        );
                        if ($ch['explanation']) {
                            $tmp .= ' ' . $ch['explanation'];
                        }
                        $dts[] = $tmp;
                    }
                    $dt_string = implode('<br />', $dts);
                    $this->tpl->setVariable('VAL_CUST', $dt_string) ;
                    $this->tpl->parseCurrentBlock();
                    break;
                    
                case 'prtf':
                    $tmp = array();
                    if (is_array($a_set['prtf'])) {
                        foreach ($a_set['prtf'] as $prtf_url => $prtf_txt) {
                            $tmp[] = '<a href="' . $prtf_url . '">' . $prtf_txt . '</a>';
                        }
                    }
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', (string) implode('<br />', $tmp)) ;
                    $this->tpl->parseCurrentBlock();
                    break;
                    
                case 'odf_last_update':
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', (string) $a_set['odf_info_txt']);
                    $this->tpl->parseCurrentBlock();
                    break;
                
                case 'roles':
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', (string) $a_set['roles_label']);
                    $this->tpl->parseCurrentBlock();
                    break;
                    
                case 'org_units':
                    $this->tpl->setCurrentBlock('custom_fields');
                    include_once './Modules/OrgUnit/classes/PathStorage/class.ilOrgUnitPathStorage.php';
                    $this->tpl->setVariable('VAL_CUST', (string) ilOrgUnitPathStorage::getTextRepresentationOfUsersOrgUnits($a_set['usr_id']));
                    $this->tpl->parseCurrentBlock();
                    break;

                // fau: campoSub: show module column
                case 'module':
                    $this->addModuleCell($a_set);
                    break;
                // fau.

                // fau: campoCheck: show restrictions column
                case 'restrictions_passed':
                    $this->addRestrictionsCell($a_set);
                    break;
                // fau.

                // fau: userData - format table output of studydata and educations
                case 'studydata':
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', nl2br($a_set['studydata']));
                    $this->tpl->parseCurrentBlock();
                    break;

                case 'educations':
                    //ilTooltipGUI::addTooltip($cell_id, nl2br($a_set['educations']),'','bottom center','top center',false);
                    $this->tpl->setCurrentBlock('custom_fields');
                    //$this->tpl->setVariable('ID_CUST', $cell_id);
                    $this->tpl->setVariable('VAL_CUST', fauTextViewGUI::getInstance()->showWithModal(
                        nl2br($a_set['educations']),
                        $this->lng->txt('fau_educations_of') . ' ' . $a_set['firstname'] . ' ' . $a_set['lastname'],
                        50
                    ));
                    $this->tpl->parseCurrentBlock();
                    break;
                // fau.

                default:
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST', isset($a_set[$field]) ? (string) $a_set[$field] : '');
                    $this->tpl->parseCurrentBlock();
                    break;
            }
        }
        
        if ($this->privacy->enabledGroupAccessTimes()) {
            $this->tpl->setVariable('VAL_ACCESS', $a_set['access_time']);
        }
        
        if ($this->show_learning_progress) {
            $this->tpl->setCurrentBlock('lp');
            $icons = ilLPStatusIcons::getInstance(ilLPStatusIcons::ICON_VARIANT_LONG);
            $icon_rendered = $icons->renderIconForStatus($icons->lookupNumStatus($a_set['progress']));

            $this->tpl->setVariable('LP_STATUS_ALT', $this->lng->txt($a_set['progress']));
            $this->tpl->setVariable('LP_STATUS_ICON', $icon_rendered);

            $this->tpl->parseCurrentBlock();
        }
        
        $this->tpl->setVariable('VAL_POSTNAME', 'participants');

        if ($this->getParticipants()->isAdmin($a_set['usr_id'])) {
            $this->tpl->setVariable('VAL_CONTACT_ID', $a_set['usr_id']);
            $this->tpl->setVariable(
                'VAL_CONTACT_CHECKED',
                $a_set['contact'] ? 'checked="checked"' : ''
            );
        }

        if (
            $this->getParticipants()->isAdmin($a_set['usr_id'])
        ) {
            $this->tpl->setVariable('VAL_NOTIFICATION_ID', $a_set['usr_id']);
            $this->tpl->setVariable(
                'VAL_NOTIFICATION_CHECKED',
                $a_set['notification'] ? 'checked="checked"' : ''
            );
        }

        $this->showActionLinks($a_set);
        
        
        $this->tpl->setVariable('VAL_LOGIN', $a_set['login']);
    }
    
    /**
     * Parse user data
     * @param array $a_user_data
     * @return
     */
    public function parse()
    {
        $this->determineOffsetAndOrder(true);
        
        $part = ilGroupParticipants::_getInstanceByObjId($this->getRepositoryObject()->getId())->getParticipants();
        
        $part = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
            'manage_members',
            'manage_members',
            $this->getRepositoryObject()->getRefId(),
            $part
        );
        
        if (!$part) {
            $this->setData(array());
            return;
        }
        

        $group_user_data = (array) $this->getParentObject()->readMemberData(
            $part,
            $this->getSelectedColumns()
        );
        
        include_once './Services/User/classes/class.ilUserQuery.php';
        
        $additional_fields = $this->getSelectedColumns();
        unset($additional_fields["firstname"]);
        unset($additional_fields["lastname"]);
        unset($additional_fields["last_login"]);
        unset($additional_fields["access_until"]);
        unset($additional_fields['consultation_hour']);
        unset($additional_fields['prtf']);
        unset($additional_fields['roles']);
        unset($additional_fields['org_units']);

        // fau: campoSub - don't query for module by default
        // fau: campoCheck - don't query for restrictions by default
        unset($additional_fields["module"]);
        unset($additional_fields["restrictions_passed"]);
        // fau.


        $udf_ids = $usr_data_fields = $odf_ids = array();
        foreach ($additional_fields as $field) {
            if (substr($field, 0, 3) == 'udf') {
                $udf_ids[] = substr($field, 4);
                continue;
            }
            if (substr($field, 0, 3) == 'odf') {
                $odf_ids[] = substr($field, 4);
                continue;
            }
            
            $usr_data_fields[] = $field;
        }

        // fau: userData - add ref_id as argument to filter the list of educations
        $usr_data = ilUserQuery::getUserListData(
            '',
            '',
            0,
            9999,
            $this->current_filter['login'],
            '',
            null,
            false,
            false,
            0,
            0,
            null,
            $usr_data_fields,
            $part,
            '',
            null,
            $this->getRepositoryObject()->getRefId()
        );
        // fau.
        
        $a_user_data = array();
        $filtered_user_ids = array();
        $local_roles = $this->getParentObject()->getLocalRoles();
        foreach ((array) $usr_data['set'] as $ud) {
            $user_id = $ud['usr_id'];
            if ($this->current_filter['roles']) {
                if (!$GLOBALS['DIC']['rbacreview']->isAssigned($user_id, $this->current_filter['roles'])) {
                    continue;
                }
            }
            if ($this->current_filter['org_units']) {
                $org_unit = $this->current_filter['org_units'];
                include_once './Modules/OrgUnit/classes/class.ilObjOrgUnitTree.php';
                $assigned = ilObjOrgUnitTree::_getInstance()->getOrgUnitOfUser($user_id);
                if (!in_array($org_unit, $assigned)) {
                    continue;
                }
            }
            
            $filtered_user_ids[] = $user_id;
            $a_user_data[$user_id] = array_merge($ud, (array) $group_user_data[$user_id]);

            $roles = array();
            foreach ($local_roles as $role_id => $role_name) {
                // @todo fix performance
                if ($GLOBALS['DIC']['rbacreview']->isAssigned($user_id, $role_id)) {
                    $roles[] = $role_name;
                }
            }
            $a_user_data[$user_id]['name'] = $a_user_data[$user_id]['lastname'] . ', ' . $a_user_data[$user_id]['firstname'];
            $a_user_data[$user_id]['roles_label'] = implode('<br />', $roles);
            $a_user_data[$user_id]['roles'] = $this->participants->setRoleOrderPosition($user_id);
        }

        // Custom user data fields
        if ($udf_ids) {
            include_once './Services/User/classes/class.ilUserDefinedData.php';
            $data = ilUserDefinedData::lookupData($filtered_user_ids, $udf_ids);
            foreach ($data as $usr_id => $fields) {
                if (!$this->checkAcceptance($usr_id)) {
                    continue;
                }
                
                foreach ($fields as $field_id => $value) {
                    $a_user_data[$usr_id]['udf_' . $field_id] = $value;
                }
            }
        }
        // Object specific user data fields
        if ($odf_ids) {
            include_once './Modules/Course/classes/Export/class.ilCourseUserData.php';
            $data = ilCourseUserData::_getValuesByObjId($this->getRepositoryObject()->getId());
            foreach ($data as $usr_id => $fields) {
                if (!$this->checkAcceptance($usr_id)) {
                    continue;
                }
                
                foreach ($fields as $field_id => $value) {
                    if ($a_user_data[$usr_id]) {
                        $a_user_data[$usr_id]['odf_' . $field_id] = $value;
                    }
                }
            }
            // add last edit date
            include_once './Services/Membership/classes/class.ilObjectCustomUserFieldHistory.php';
            foreach (ilObjectCustomUserFieldHistory::lookupEntriesByObjectId($this->getRepositoryObject()->getId()) as $usr_id => $edit_info) {
                if (!isset($a_user_data[$usr_id])) {
                    continue;
                }
                
                include_once './Services/PrivacySecurity/classes/class.ilPrivacySettings.php';
                if ($usr_id == $edit_info['update_user']) {
                    $a_user_data[$usr_id]['odf_last_update'] = '';
                    $a_user_data[$usr_id]['odf_info_txt'] = $GLOBALS['DIC']['lng']->txt('cdf_edited_by_self');
                    if (ilPrivacySettings::_getInstance()->enabledAccessTimesByType($this->getRepositoryObject()->getType())) {
                        $a_user_data[$usr_id]['odf_last_update'] .= ('_' . $edit_info['editing_time']->get(IL_CAL_UNIX));
                        $a_user_data[$usr_id]['odf_info_txt'] .= (', ' . ilDatePresentation::formatDate($edit_info['editing_time']));
                    }
                } else {
                    $a_user_data[$usr_id]['odf_last_update'] = $edit_info['edit_user'];
                    $a_user_data[$usr_id]['odf_last_update'] .= ('_' . $edit_info['editing_time']->get(IL_CAL_UNIX));
                    
                    $name = ilObjUser::_lookupName($edit_info['update_user']);
                    $a_user_data[$usr_id]['odf_info_txt'] = ($name['firstname'] . ' ' . $name['lastname'] . ', ' . ilDatePresentation::formatDate($edit_info['editing_time']));
                }
            }
        }

        // consultation hours
        if ($this->isColumnSelected('consultation_hour')) {
            include_once './Services/Booking/classes/class.ilBookingEntry.php';
            foreach (ilBookingEntry::lookupManagedBookingsForObject($this->getRepositoryObject()->getId(), $GLOBALS['DIC']['ilUser']->getId()) as $buser => $booking) {
                if (isset($a_user_data[$buser])) {
                    $a_user_data[$buser]['consultation_hour'] = $booking[0]['dt'];
                    $a_user_data[$buser]['consultation_hour_end'] = $booking[0]['dtend'];
                    $a_user_data[$buser]['consultation_hours'] = $booking;
                }
            }
        }

        // fau: campoSub - add the data for the module column
        // fau: campoCheck - add the data for the restrictions column
        $this->addCampoData($a_user_data);
        // fau.

        // always sort by name first
        $a_user_data = ilUtil::sortArray(
            $a_user_data,
            'name',
            $this->getOrderDirection()
        );
        
        return $this->setData($a_user_data);
    }
}
