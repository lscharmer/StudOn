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


/**
* adapter class for nusoap server
*
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id$
*
* @package ilias
*/
require_once('./libs/composer/vendor/autoload.php');
use ILIAS\BackgroundTasks\Implementation\TaskManager\AsyncTaskManager;
use ILIAS\OrgUnit\Webservices\SOAP\AddUserIdToPositionInOrgUnit;
use ILIAS\OrgUnit\Webservices\SOAP\Base;
use ILIAS\OrgUnit\Webservices\SOAP\EmployeePositionId;
use ILIAS\OrgUnit\Webservices\SOAP\ImportOrgUnitTree;
use ILIAS\OrgUnit\Webservices\SOAP\OrgUnitTree;
use ILIAS\OrgUnit\Webservices\SOAP\PositionIds;
use ILIAS\OrgUnit\Webservices\SOAP\PositionTitle;
use ILIAS\OrgUnit\Webservices\SOAP\RemoveUserIdFromPositionInOrgUnit;
use ILIAS\OrgUnit\Webservices\SOAP\SuperiorPositionId;
use ILIAS\OrgUnit\Webservices\SOAP\UserIdsOfPosition;
use ILIAS\OrgUnit\Webservices\SOAP\UserIdsOfPositionAndOrgUnit;

include_once './webservice/soap/lib/nusoap.php';
include_once './webservice/soap/include/inc.soap_functions.php';
require_once('./Services/WebServices/SOAP/classes/class.ilSoapHook.php');
require_once('./Services/Init/classes/class.ilInitialisation.php');

class ilNusoapUserAdministrationAdapter
{
    /*
     * @var object Nusoap-Server
     */
    public $server = null;


    public function __construct($a_use_wsdl = true)
    {
        define('SERVICE_NAME', 'ILIASSoapWebservice');
        define('SERVICE_NAMESPACE', 'urn:ilUserAdministration');
        define('SERVICE_STYLE', 'rpc');
        define('SERVICE_USE', 'encoded');
        $this->server = new soap_server();
        $this->server->decode_utf8 = false;
        $this->server->class = "ilSoapFunctions";
        
        if ($a_use_wsdl) {
            $this->__enableWSDL();
        }

        $this->__registerMethods();
    }

    public function start()
    {
        $postdata = file_get_contents("php://input");
        $this->server->service($postdata);
        exit();
    }

    // PRIVATE
    public function __enableWSDL()
    {
        $this->server->configureWSDL(SERVICE_NAME, SERVICE_NAMESPACE);

        return true;
    }


    public function __registerMethods()
    {

        // Add useful complex types. E.g. array("a","b") or array(1,2)
        $this->server->wsdl->addComplexType(
            'intArray',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType','wsdl:arrayType' => 'xsd:int[]')),
            'xsd:int'
        );

        $this->server->wsdl->addComplexType(
            'stringArray',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType','wsdl:arrayType' => 'xsd:string[]')),
            'xsd:string'
        );

        $this->server->wsdl->addComplexType(
            'doubleArray',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType','wsdl:arrayType' => 'xsd:double[]')),
            'xsd:double'
        );

        // It's not possible to register classes in nusoap

        // login()
        $this->server->register(
            'login',
            array('client' => 'xsd:string',
                                      'username' => 'xsd:string',
                                      'password' => 'xsd:string'),
            array('sid' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#login',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS login function'
        );

        // loginCAS()
        $this->server->register(
            'loginCAS',
            array('client' => 'xsd:string',
                                      'PT' => 'xsd:string',
                                      'user' => 'xsd:string'),
            array('sid' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#loginCAS',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS login function via CAS'
        );
        // loginLDAP()
        $this->server->register(
            'loginLDAP',
            array('client' => 'xsd:string',
                                      'username' => 'xsd:string',
                                      'password' => 'xsd:string'),
            array('sid' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#login',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS login function via LDAP'
        );

        // loginStudipUser()
        $this->server->register(
            'loginStudipUser',
            array('sid' => 'xsd:string',
                                    'user_id' => 'xsd:int'),
            array('sid' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#loginStudipUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS login function for Stud.IP-Connection. DEPRECATED: this method will be removed in ILIAS 5.3.'
        );

        // logout()
        $this->server->register(
            'logout',
            array('sid' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#logout',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS logout function'
        );
        // user_data definitions
        $this->server->wsdl->addComplexType(
            'ilUserData',
            'complexType',
            'struct',
            'all',
            '',
            array('usr_id' => array('name' => 'usr_id','type' => 'xsd:int'),
                                                  'login' => array('name' => 'login', 'type' => 'xsd:string'),
                                                  'passwd' => array('name' => 'passwd', 'type' => 'xsd:string'),
                                                  'firstname' => array('name' => 'firstname', 'type' => 'xsd:string'),
                                                  'lastname' => array('name' => 'lastname', 'type' => 'xsd:string'),
                                                  'title' => array('name' => 'title', 'type' => 'xsd:string'),
                                                  'gender' => array('name' => 'gender', 'type' => 'xsd:string'),
                                                  'email' => array('name' => 'email', 'type' => 'xsd:string'),
                                                  'second_email' => array('name' => 'second_email', 'type' => 'xsd:string'),
                                                  'institution' => array('name' => 'institution', 'type' => 'xsd:string'),
                                                  'street' => array('name' => 'street', 'type' => 'xsd:string'),
                                                  'city' => array('name' => 'city', 'type' => 'xsd:string'),
                                                  'zipcode' => array('name' => 'zipcode', 'type' => 'xsd:string'),
                                                  'country' => array('name' => 'country', 'type' => 'xsd:string'),
                                                  'phone_office' => array('name' => 'phone_office', 'type' => 'xsd:string'),
                                                  'last_login' => array('name' => 'last_login', 'type' => 'xsd:string'),
                                                  'last_update' => array('name' => 'last_update', 'type' => 'xsd:string'),
                                                  'create_date' => array('name' => 'create_date', 'type' => 'xsd:string'),
                                                  'hobby' => array('name' => 'hobby', 'type' => 'xsd:string'),
                                                  'department' => array('name' => 'department', 'type' => 'xsd:string'),
                                                  'phone_home' => array('name' => 'phone_home', 'type' => 'xsd:string'),
                                                  'phone_mobile' => array('name' => 'phone_mobile', 'type' => 'xsd:string'),
                                                  'fax' => array('name' => 'fax', 'type' => 'xsd:string'),
                                                  'time_limit_owner' => array('name' => 'time_limit_owner', 'type' => 'xsd:int'),
                                                  'time_limit_unlimited' => array('name' => 'time_limit_unlimited', 'type' => 'xsd:int'),
                                                  'time_limit_from' => array('name' => 'time_limit_from', 'type' => 'xsd:int'),
                                                  'time_limit_until' => array('name' => 'time_limit_until', 'type' => 'xsd:int'),
                                                  'time_limit_message' => array('name' => 'time_limit_message', 'type' => 'xsd:int'),
                                                  'referral_comment' => array('name' => 'referral_comment', 'type' => 'xsd:string'),
                                                  'matriculation' => array('name' => 'matriculation', 'type' => 'xsd:string'),
                                                  'active' => array('name' => 'active', 'type' => 'xsd:int'),
                                                  'accepted_agreement' => array('name' => 'accepted_agreement','type' => 'xsd:boolean'),
                                                  'approve_date' => array('name' => 'approve_date', 'type' => 'xsd:string'),
                                                  'user_skin' => array('name' => 'user_skin', 'type' => 'xsd:string'),
                                                  'user_style' => array('name' => 'user_style', 'type' => 'xsd:string'),
                                                  'user_language' => array('name' => 'user_language', 'type' => 'xsd:string'),
                                                  'import_id' => array('name' => 'import_id', 'type' => 'xsd:string')
                                                  )
        );


        // lookupUser()
        $this->server->register(
            'lookupUser',
            array('sid' => 'xsd:string',
                                      'user_name' => 'xsd:string'),
            array('usr_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#lookupUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS lookupUser(): check if username exists. Return usr_id or 0 if lookup fails.'
        );


        // getUser()
        $this->server->register(
            'getUser',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int'),
            array('user_data' => 'tns:ilUserData'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUser(): get complete set of user data. DEPRECATED with release 5.2, will be deleted with 5.3. Use searchUsers() instead.'
        );

        // deleteUser()
        $this->server->register(
            'deleteUser',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteUser(). Deletes all user related data (Bookmarks, Mails ...). DEPRECATED: Use importUsers() for deletion of user data.'
        );

        // addCourse()
        $this->server->register(
            'addCourse',
            array('sid' => 'xsd:string',
                                      'target_id' => 'xsd:int',
                                      'crs_xml' => 'xsd:string'),
            array('course_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addCourse',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addCourse(). Course import. See ilias_course_0_1.dtd for details about course xml structure'
        );

        // deleteCourse()
        $this->server->register(
            'deleteCourse',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteCourse',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteCourse(). Deletes a course. Delete courses are stored in "Trash" and can be undeleted in ' .
                                ' the ILIAS administration. '
        );
        // startBackgroundTaskWorker()
        $this->server->register(
            AsyncTaskManager::CMD_START_WORKER,
            array('sid' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#' . AsyncTaskManager::CMD_START_WORKER,
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS ' . AsyncTaskManager::CMD_START_WORKER . '().'
        );

        // assignCourseMember()
        $this->server->register(
            'assignCourseMember',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int',
                                      'user_id' => 'xsd:int',
                                      'type' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#assignCourseMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS assignCourseMember(). Assigns an user to an existing course. Type should be "Admin", "Tutor" or "Member"'
        );

        // excludeCourseMember()
        $this->server->register(
            'excludeCourseMember',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#excludeCourseMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS excludeCourseMember(). Excludes an user from an existing course.'
        );

        // isAssignedToCourse()
        $this->server->register(
            'isAssignedToCourse',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('role' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#isAssignedToCourse',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS isAssignedToCourse(). Checks whether an user is assigned to a given course. ' .
                                'Returns 0 => not assigned, 1 => course admin, 2 => course member or 3 => course tutor'
        );

        // getCourseXML($sid,$course_id)
        $this->server->register(
            'getCourseXML',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getCourseXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getCourseXML(). Get a xml description of a specific course.'
        );

        // updateCourse($sid,$course_id,$xml)
        $this->server->register(
            'updateCourse',
            array('sid' => 'xsd:string',
                                      'course_id' => 'xsd:int',
                                      'xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateCourse',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateCourse(). Update course settings, assigned members, tutors, administrators with a ' .
                                'given xml description'
        );

        // get obj_id by import id
        $this->server->register(
            'getObjIdByImportId',
            array('sid' => 'xsd:string',
                                      'import_id' => 'xsd:string'),
            array('obj_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getCourseIdByImportId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getObjIdByImportId(). Get the obj_id of an ILIAS obj by a given import id.'
        );


        // get ref ids by import id
        $this->server->register(
            'getRefIdsByImportId',
            array('sid' => 'xsd:string',
                                      'import_id' => 'xsd:string'),
            array('ref_ids' => 'tns:intArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getRefIdsByImportId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getRefIdsByImportId(). Get all reference ids by a given import id.'
        );

        // get obj_id by import id
        $this->server->register(
            'getRefIdsByObjId',
            array('sid' => 'xsd:string',
                                      'obj_id' => 'xsd:string'),
            array('ref_ids' => 'tns:intArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getRefIdsByObjId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getRefIdsByObjId(). Get all reference ids by a given object id.'
        );

        // Object administration
        $this->server->register(
            'getObjectByReference',
            array('sid' => 'xsd:string',
                                      'reference_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('object_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getObjectByReference',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getObjectByReference(). Get XML-description of an ILIAS object. If a user id is given, ' .
                                'this methods also checks the permissions of that user on the object.'
        );

        $this->server->register(
            'getObjectsByTitle',
            array('sid' => 'xsd:string',
                                      'title' => 'xsd:string',
                                      'user_id' => 'xsd:int'),
            array('object_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getObjectsByTitle',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getObjectsByTitle(). Get XML-description of an ILIAS object with given title. ' .
                                'If a user id is given this method also checks the permissions of that user on the object.'
        );

        $this->server->register(
            'searchObjects',
            array('sid' => 'xsd:string',
                                      'types' => 'tns:stringArray',
                                      'key' => 'xsd:string',
                                      'combination' => 'xsd:string',
                                      'user_id' => 'xsd:int'),
            array('object_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#searchObjects',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS searchObjects(): Searches for objects. Key is within "title" or "description" ' .
                                'Typical calls are searchObject($sid,array("lm","crs"),"\"this and that\"","and"); ' .
                                ' If an optional user id is given, this methods also return the permissions for that user ' .
                                'on the found objects'
        );

        $this->server->register(
            'getTreeChilds',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int',
                                      'types' => 'tns:stringArray',
                                      'user_id' => 'xsd:int'),
            array('object_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getTreeChilds',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getTreeChilds(): Get all child objects of a given object.' .
                                'Choose array of types to filter the output. Choose empty type array to receive all object types'
        );

        $this->server->register(
            'getXMLTree',
            array('sid' => 'xsd:string',
                          'ref_id' => 'xsd:int',
                          'types' => 'tns:stringArray',
                          'user_id' => 'xsd:int'),
            array('object_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getXMLTree',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getXMLTree(): Returns a xml stream with the subtree objects.'
        );



        $this->server->register(
            'addObject',
            array('sid' => 'xsd:string',
                                      'target_id' => 'xsd:int',
                                      'object_xml' => 'xsd:string'),
            array('ref_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addObject',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addObject. Create new object based on xml description under a given node ' .
                                '("category,course,group or folder). Return created reference id of the new object.'
        );

        $this->server->register(
            'updateObjects',
            array('sid' => 'xsd:string',
                                      'object_xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateObjects',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateObjects. Update object data (title,description,owner)'
        );

        $this->server->register(
            'addReference',
            array('sid' => 'xsd:string',
                                      'source_id' => 'xsd:int',
                                      'target_id' => 'xsd:int'),
            array('ref_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addReference',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addReference. Create new link of given object to new object. Return the new reference id'
        );

        $this->server->register(
            'deleteObject',
            array('sid' => 'xsd:string',
                                      'reference_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteObject',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteObject. Stores object in trash. If multiple references exist, only the reference is deleted '
        );


        $this->server->register(
            'removeFromSystemByImportId',
            array('sid' => 'xsd:string',
                                      'import_id' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#removeFromSystemByImportId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS removeFromSystemByImportId(). Removes an object identified by its import id permanently from the ' .
                                'system. All data will be deleted. There will be no possibility to restore it from the trash. Do not use ' .
                                'this function for deleting roles or users. Use deleteUser() or deleteRole() instead.'
        );

        $this->server->register(
            'addUserRoleEntry',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int',
                                      'role_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addUserRoleEntry',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addUserRoleEntry. Assign user to role.'
        );

        $this->server->register(
            'deleteUserRoleEntry',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int',
                                      'role_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteUserRoleEntry',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteUserRoleEntry. Deassign user from role.'
        );


        // Add complex type for operations e.g array(array('name' => 'read','ops_id' => 2),...)
        $this->server->wsdl->addComplexType(
            'ilOperation',
            'complexType',
            'struct',
            'all',
            '',
            array('ops_id' => array('name' => 'ops_id',
                                                                    'type' => 'xsd:int'),
                                                  'operation' => array('name' => 'operation',
                                                                       'type' => 'xsd:string'),
                                                  'description' => array('name' => 'description',
                                                                         'type' => 'xsd:string'))
        );
        // Now create an array of ilOperations
        $this->server->wsdl->addComplexType(
            'ilOperations',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType',
                                                        'wsdl:arrayType' => 'tns:ilOperation[]')),
            'tns:ilOperation'
        );
        $this->server->register(
            'getOperations',
            array('sid' => 'xsd:string'),
            array('operations' => 'tns:ilOperations'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getOperations',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getOperations(): get complete set of RBAC operations.'
        );

        $this->server->register(
            'revokePermissions',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int',
                                      'role_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#revokePermissions',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS revokePermissions(): Revoke all permissions for a specific role on an object.'
        );

        $this->server->wsdl->addComplexType(
            'ilOperationIds',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType',
                                                        'wsdl:arrayType' => 'xsd:int[]')),
            'xsd:int'
        );

        $this->server->register(
            'grantPermissions',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int',
                                      'role_id' => 'xsd:int',
                                      'operations' => 'tns:intArray'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#grantPermissions',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS grantPermissions(): Grant permissions for a specific role on an object. ' .
                                '(Substitutes existing permission settings)'
        );

        $this->server->register(
            'getLocalRoles',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int'),
            array('role_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getLocalRoles',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getLocalRoles(): Get all local roles assigned to an specific object.'
        );

        $this->server->register(
            'getUserRoles',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int'),
            array('role_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getLocalRoles',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUserRoles(): Get all local roles assigned to an specific user. '
        );

        $this->server->register(
            'addRole',
            array('sid' => 'xsd:string',
                                      'target_id' => 'xsd:int',
                                      'obj_xml' => 'xsd:string'),
            array('role_ids' => 'tns:intArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addRole',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addRole(): Creates new role under given node. "target_id" is the reference id of an ILIAS ' .
                                'ILIAS object. E.g ref_id of crs,grp. If no role folder exists, a new role folder will be created.'
        );

        $this->server->register(
            'deleteRole',
            array('sid' => 'xsd:string',
                                      'role_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteRole',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteRole(): Deletes an role and all user assignments. Fails if it is the last role of an user'
        );

        $this->server->register(
            'addRoleFromTemplate',
            array('sid' => 'xsd:string',
                                      'target_id' => 'xsd:int',
                                      'obj_xml' => 'xsd:string',
                                      'role_template_id' => 'xsd:int'),
            array('role_ids' => 'tns:intArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addRole',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addRole(): Creates new role under given node. "target_id" is the reference id of an ILIAS ' .
                                'ILIAS object. E.g ref_id of crs,grp. If no role folder exists, a new role folder will be created. ' .
                                'In addition to addRole the template permissions will be copied from the given role template'
        );

        $this->server->register(
            'getObjectTreeOperations',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('operations' => 'tns:ilOperations'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getPermissionsForObject',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getObjectTreeOperations(): Get all granted permissions for all references of ' .
                                'an object for a specific user. Returns array of granted operations or empty array'
        );

        $this->server->register(
            'addGroup',
            array('sid' => 'xsd:string',
                                      'target_id' => 'xsd:int',
                                      'group_xml' => 'xsd:string'),
            array('ref_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addGroup',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addGroup(): Add grop according to valid group XML ' .
                                '@See ilias_group_0_1.dtd'
        );

        $this->server->register(
            'groupExists',
            array('sid' => 'xsd:string',
                                      'title' => 'xsd:string'),
            array('exists' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#groupExists',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addGroup(): Check if group with given name exists. '
        );


        // getGroup
        $this->server->register(
            'getGroup',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int'),
            array('group_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getGroup',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getGroup(): get xml description of grouip with given reference id.'
        );

        // assignGroupMember()
        $this->server->register(
            'assignGroupMember',
            array('sid' => 'xsd:string',
                                      'group_id' => 'xsd:int',
                                      'user_id' => 'xsd:int',
                                      'type' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#assignGroupMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS assignGroupMember(). Assigns an user to an existing group. Type should be "Admin","Member"'
        );

        // excludeGroupMember()
        $this->server->register(
            'excludeGroupMember',
            array('sid' => 'xsd:string',
                                      'group_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#excludeGroupMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS excludeGroupMember(). Excludes an user from an existing group.'
        );

        // isAssignedToGroup()
        $this->server->register(
            'isAssignedToGroup',
            array('sid' => 'xsd:string',
                                      'group_id' => 'xsd:int',
                                      'user_id' => 'xsd:int'),
            array('role' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#isAssignedToGroup',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS isAssignedToGroup(). Checks whether an user is assigned to a given group. ' .
                                'Returns 0 => not assigned, 1 => group admin, 2 => group member'
        );



        // ILIAS util functions
        $this->server->register(
            'distributeMails',
            array('sid' => 'xsd:string',
                                      'mail_xml' => 'xsd:string'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#sendMail',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS distributeMails(): Distribute ILIAS mails according according to the mail setting of the recipients as ' .
                                'ILIAS internal mail or as e-mail.'
                                );

        // fau: sendSimpleResults - register function to send user mails
        $this->server->register(
            'sendUserMail',
            array('sid' => 'xsd:string',
                                      'rcp_to' => 'xsd:string',
                                      'rcp_cc' => 'xsd:string',
                                      'rcp_bcc' => 'xsd:string',
                                      'sender' => 'xsd:string',
                                      'subject' => 'xsd:string',
                                      'message' => 'xsd:string',
                                      'attachments' => 'xsd:string',
                                      'type' => 'xsd:string',
                                      'use_placholders' => 'xsd:boolean'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#sendUserMail',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS sendUserMail(): Send internal mails according to xml description. Only for internal usage ' .
                                'Syntax, parameters may change in future releases'
        );
        // fau.


        // Clone functions
        $this->server->register(
            'ilClone',
            array('sid' => 'xsd:string','copy_identifier' => 'xsd:int'),
            array('new_ref_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#ilClone',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS ilClone(): Only for internal usage.' .
                                'Syntax, parameters may change in future releases. '
        );

        $this->server->register(
            'handleECSTasks',
            array('sid' => 'xsd:string','server_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#handleECSTasks',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS handleECSTasks(): Only for internal usage.' .
                                'Syntax, parameters may change in future releases. '
        );

        $this->server->register(
            'ilCloneDependencies',
            array('sid' => 'xsd:string','copy_identifier' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#ilCloneDependencies',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS ilCloneDependencies(): Only for internal usage.' .
                                'Syntax, parameters may change in future releases. '
        );

        $this->server->register(
            'saveQuestionResult',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:int',
                                      'test_id' => 'xsd:int',
                                      'question_id' => 'xsd:int',
                                      'pass' => 'xsd:int',
                                      'solution' => 'tns:stringArray'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#saveQuestionResult',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS saveQuesionResult: Typically called from an external assessment question to save the user input. DEPRECATED since ILIAS 3.9'
        );

        $this->server->register(
            'saveQuestion',
            array('sid' => 'xsd:string',
                                      'active_id' => 'xsd:long',
                                      'question_id' => 'xsd:long',
                                      'pass' => 'xsd:int',
                                      'solution' => 'tns:stringArray'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#saveQuestion',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS saveQuestion: Saves the result of a question in a given test pass for the active test user. The active user is identified by the active ID, which assigns a user to a test.'
        );

        $this->server->register(
            'saveQuestionSolution',
            array('sid' => 'xsd:string',
                                  'active_id' => 'xsd:long',
                                  'question_id' => 'xsd:long',
                                  'pass' => 'xsd:int',
                                  'solution' => 'xsd:string'),
            array('status' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#saveQuestionSolution',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS saveQuestionSolution: Saves the result of a question in a given test pass for the active test user. The active user is identified by the active ID, which assigns a user to a test. The solution has to be an XML string which contains &lt;values&gt;&lt;value&gt;VALUE&lt;/value&gt;&lt;value&gt;VALUE&lt;/value&gt;&lt;points&gt;POINTS&lt;/points&gt;...&lt;/values&gt; where the triplet (value,value,points) can repeat n times. The result string is either TRUE or it contains an error message.'
        );

        $this->server->register(
            'getQuestionSolution',
            array('sid' => 'xsd:string',
                                      'active_id' => 'xsd:long',
                                      'question_id' => 'xsd:int',
                                      'pass' => 'xsd:int'),
            array('solution' => 'tns:stringArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getQuestionSolution',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getQuestionSolution: Typically called from external assessment questions to retrieve the previous input of a user.'
        );

        $this->server->register(
            'getTestUserData',
            array('sid' => 'xsd:string',
                                                          'active_id' => 'xsd:long'),
            array('userdata' => 'tns:stringArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getTestUserData',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getTestUserData: Typically called from external assessment questions to retrieve data of the active user. The returned string array values are fullname, title, firstname, lastname, login.'
        );

        $this->server->register(
            'getPositionOfQuestion',
            array('sid' => 'xsd:string',
                                                          'active_id' => 'xsd:long',
                                                          'question_id' => 'xsd:int',
                                                          'pass' => 'xsd:int'),
            array('position' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getPositionOfQuestion',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getPositionOfQuestion: Returns the position of a given question for a given user in a given test pass.'
        );

        $this->server->register(
            'getPreviousReachedPoints',
            array('sid' => 'xsd:string',
                                                          'active_id' => 'xsd:long',
                                                          'question_id' => 'xsd:int',
                                                          'pass' => 'xsd:int'),
            array('position' => 'tns:doubleArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getPreviousReachedPoints',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getPreviousReachedPoints: Returns an array of reached points for the previous questions in a given test pass.'
        );

        $this->server->register(
            'getNrOfQuestionsInPass',
            array('sid' => 'xsd:string',
                                                          'active_id' => 'xsd:long',
                                                          'pass' => 'xsd:int'),
            array('count' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getNrOfQuestionsInPass',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getNrOfQuestionsInPass: Returns the question count for a given test user in a given pass.'
        );

        $this->server->register(
            'getStructureObjects',
            array('sid' => 'xsd:string',
                                      'ref_id' => 'xsd:int'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getStructureObjects',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getStructureObjects: delivers structure of content objects like learning modules (chapters/pages) or glossary (terms)'
        );

        // importUsers()
        $this->server->register(
            'importUsers',
            array('sid' => 'xsd:string',
                                      'folder_id' => 'xsd:int',
                                      'usr_xml' => 'xsd:string',
                                      'conflict_rule' => 'xsd:int',
                                      'send_account_mail' => 'xsd:int'),
            array('protocol' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#importUsers',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS import users into folder id, which should be ref_id of folder or user folder (-1:System user folder, 0: checks access at user level, otherwise refid): conflict_rule: IL_FAIL_ON_CONFLICT = 1, IL_UPDATE_ON_CONFLICT = 2, IL_IGNORE_ON_CONFLICT = 3. The Return-Value is a protocol with the columns userid, login, action, message, following xmlresultset dtd. Send Account Mail = 0 deactivates sending a mail to each user, 1 activates it'
        );

        $this->server->register(
            'getRoles',
            array('sid' => 'xsd:string',
                                      'role_type' => 'xsd:string',
                                      'id' => 'xsd:string'),
            array('role_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getRoles',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getRoles():if id equals -1, get all roles specified by type (global|local|user|user_login|template or empty), if type is empty all roles with all types are delivered, if id > -1 and role_type <> user or user_login, delivers all roles which belong to a repository object with specified ref_id, if roletype is user a numeric id is interpreted as userid, if roletype is user_login it is interpreted as login,if roletype is template all role templates will be listed'
        );

        $this->server->register(
            'getUsersForContainer',
            array('sid' => 'xsd:string',
                                'ref_id' => 'xsd:int',
                                     'attach_roles' => 'xsd:int',
                                    'active' => 'xsd:int'),
            array('user_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getUsersForContainer',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUsersForContainer(): get all users of a specific ref_id, which can be crs, group, category or user folder (value: -1). Choose if all roles of a user should be attached (1) or not (0). set active to -1 to get all, 0, to get inactive users only, 1 to get active users only'
        );

        $this->server->register(
            'getUsersForRole',
            array('sid' => 'xsd:string',
                                      'role_id' => 'xsd:int',
                                      'attach_roles' => 'xsd:int',
                                      'active' => 'xsd:int'),
            array('user_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getUsersForRole',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUsersForRole(): get all users of a role with specified id, specify attach_roles to 1, to attach all role assignmnents; specify active: 1, to import active only, 0: inactive only, -1: both'
        );

        $this->server->register(
            'searchUser',
            array('sid' => 'xsd:string',
                                      'key_fields' => 'tns:stringArray',
                                      'query_operator' => 'xsd:string',
                                      'key_values' => 'tns:stringArray',
                                      'attach_roles' => 'xsd:int',
                                      'active' => 'xsd:int'),
            array('user_xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#searchUsers',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS searchUser(): get all users, which match a query, consisting of the keyfields, matched with values of the field values, concatenated with the logical query operator. Specify attach_roles to 1, to attach all role assignmnents; specify active: 1, to import active only, 0: inactive only, -1: both'
        );

        // Mail Functions
        // Check whether current user has new mail
        $this->server->register(
            'hasNewMail',
            array('sid' => 'xsd:string'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#hasNewMail',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS hasNewMail(): Checks whether the current authenticated user has a new mail.'
        );

        $this->server->register(
            'getNIC',
            array('sid' => 'xsd:string'),
            array('xmlresultset' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getNIC',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getNIC(): DEPRECATED: use getClientInfoXML instead. was: return client information from current client as xml result set containing installation_id, installation_version, installation_url, installation_description, installation_language_default as columns'
        );

        $this->server->register(
            'getExerciseXML',
            array('sid' => 'xsd:string', "ref_id" => 'xsd:int', "attachment_mode" => "xsd:int"),
            array('exercisexml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getExerciseXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getExerciseXML(): returns xml description of exercise. Attachment mode: 0 - no file contents, 1 - plain content (base64encoded), 2 zlib + base64, 3 gzip + base64)'
        );

        $this->server->register(
            'addExercise',
            array('sid' => 'xsd:string', "target_id" => 'xsd:int', "xml" => "xsd:string"),
            array('refid' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addExercise',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addExercise(): create exercise, put it into target (ref_id) and update exercise properties from xml (see ilias_exercise_3_8.dtd for details). Obj_id must not be set!'
        );

        $this->server->register(
            'updateExercise',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateExercise',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateExercise():update existing exercise, update exercise properties from xml (see ilias_exercise_3_8.dtd for details). obj_id in xml must match according obj id of refid.!'
        );

        $this->server->register(
            'getFileXML',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'attachment_mode' => 'xsd:int'),
            array('filexml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getFileXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getFileXML(): returns xml description of file. Attachment mode: 0 - no file contents, 1 - plain content (base64encoded), 2 zlib + base64, 3 gzip + base64)'
        );

        $this->server->register(
            'addFile',
            array('sid' => 'xsd:string', 'target_id' => 'xsd:int', 'xml' => 'xsd:string'),
            array('refid' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addFile',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS addFile(): create file, put it into target (ref_id) and update file properties from xml (see ilias_file_3_8.dtd for details). Obj_id must not be set!'
        );

        $this->server->register(
            'updateFile',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateFile',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateFile():update existing file, update file properties from xml (see ilias_file_3_8.dtd for details). obj_id in xml must match according obj id of refid.!'
        );


        $this->server->register(
            'getUserXML',
            array('sid' => 'xsd:string', 'user_ids' => 'tns:intArray', 'attach_roles' => 'xsd:int'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#resolveUsers',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUserXML(): get xml records for user ids, e.g. retrieved vom members of course xml. Returns user xml dtds. ids are numeric ids of user'
        );


        // get objs ids by ref id
        $this->server->register(
            'getObjIdsByRefIds',
            array('sid' => 'xsd:string',
                                      'ref_ids' => 'tns:intArray'),
            array('obj_ids' => 'tns:intArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getRefIdsByImportId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getObjIdsForRefIds: Returns a array of object ids which match the references id, given by a comma seperated string. Returns an array of ref ids, in the same order as object ids. Therefore, there might by duplicates'
        );

        $this->server->register(
            'updateGroup',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateGroup',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateGroup(): update existing group using ref id and group xml (see DTD).'
        );


        
        $this->server->register(
            'getIMSManifestXML',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getIMSManifestXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getIMSManifestXML(): returns xml of ims manifest file (scorm learning module) referred by refid'
        );

        $this->server->register(
            'hasSCORMCertificate',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'usr_id' => 'xsd:int'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#hasSCORMCertificate',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS hasSCORMCertificate(): returns true if a certficate is available for a user referred by usr_id in a SCORM learning module referred by ref_id'
        );

        $this->server->register(
            'getSCORMCompletionStatus',
            array('sid' => 'xsd:string', 'usr_id' => 'xsd:int', 'ref_id' => 'xsd:int'),
            array('status' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getSCORMCompletionStatus',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getSCORMCompletionStatus(): returns a completion status of a scorm module'
        );

        $this->server->register(
            'copyObject',
            array('sid' => 'xsd:string', 'xml' => 'xsd:string'),
            array('xml' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#copyObject',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS copyObject(): returns reference of copy, if copy is created directly, or the ref id of the target if copy is in progress.'
        );
        
        $this->server->register(
            'moveObject',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'target_id' => 'xsd:int'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#moveObject',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS moveObject(): returns true, if object with refid could be successfully moved to target id, other it raises an error.'
        );
                                
        
        $this->server->register(
            'getTestResults',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'sum_only' => 'xsd:boolean'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getTestResults',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getTestResults(): returns XMLResultSet with 
									 sum only = true: user_id, login, firstname, lastname, matriculation, maximum points, received points
	 								 sum only = false: user_id, login, firstname, lastname, matriculation, question id, question title, question points, received points'
        );

        $this->server->register(
            'removeTestResults',
            array(
                                    'sid' => 'xsd:string',
                                    'ref_id' => 'xsd:int',
                                    'user_ids' => 'tns:intArray'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#removeTestResults',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS removeTestResults(): remove test results for the chosen users'
        );

        $this->server->register(
            'getCoursesForUser',
            array('sid' => 'xsd:string', 'parameters' => 'xsd:string'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getCoursesForUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getTestResults(): returns XMLResultSet with columns ref_id, course xml. $parameters has to contain a column user_id and a column status. Status is a logical AND combined value of (MEMBER = 1, TUTOR = 2, ADMIN = 4, OWNER = 8) and determines which courses should be returned.'
        );
                                
        $this->server->register(
            'getGroupsForUser',
            array('sid' => 'xsd:string', 'parameters' => 'xsd:string'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getGroupsForUser',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getTestResults(): returns XMLResultSet with columns ref_id, group xml. $parameters has to contain a column user_id and a column status. Status is a logical AND combined value of (MEMBER = 1, TUTOR = 2, OWNER = 4) and determines which groups should be returned.'
        );
                                
        $this->server->register(
            'getPathForRefId',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getPathForRefId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getPathForRefId(): returns XMLResultSet with columns ref_id, type and title.'
        );
                                
        $this->server->register(
            'searchRoles',
            array('sid' => 'xsd:string', 'key' => 'xsd:string', 'combination' => 'xsd:string', 'role_type' => 'xsd:string'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#searchRoles',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS searchRoles(): returns XML following role dtd with search results for given role type and search terms.'
        );
                                
        $this->server->register(
            'getInstallationInfoXML',
            array(),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getInstallationInfoXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getInstallationInfoXML(): returns XML following installation_info dtd'
        );
        
        $this->server->register(
            'getClientInfoXML',
            array('clientid' => 'xsd:string'),
            array('xml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getClientInfoXML',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getClientInfoXML(): returns XML following installation_info dtd, contains the client the data of given client id'
        );

        $this->server->register(
            'getSkillCompletionDateForTriggerRefId',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:string',
                                      'ref_id' => 'xsd:string'),
            array('dates' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getSkillCompletionDateForTriggerRefId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getSkillCompletionDateForTriggerRefId(). Get completion dates for skill trigger ref ids.'
        );

        $this->server->register(
            'checkSkillUserCertificateForTriggerRefId',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:string',
                                      'ref_id' => 'xsd:string'),
            array('have_certificates' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#checkSkillUserCertificateForTriggerRefId',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS checkSkillUserCertificateForTriggerRefId(). Check user certificates for trigger ref ids.'
        );

        $this->server->register(
            'getSkillTriggerOfAllCertificates',
            array('sid' => 'xsd:string',
                                      'user_id' => 'xsd:string'),
            array('certificate_triggers' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getSkillTriggerOfAllCertificates',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getSkillTriggerOfAllCertificates(). Check get all trigger with certificate for a user.'
        );

        $this->server->register(
            'getUserIdBySid',
            array('sid' => 'xsd:string'),
            array('usr_id' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getUserIdBySid',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getUserIdBySid(): returns an ILIAS usr_id for the given sid'
        );
                                
        $this->server->register(
            'deleteExpiredDualOptInUserObjects',
            array('sid' => 'xsd:string',
                                      'usr_id' => 'xsd:int'),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteExpiredDualOptInUserObjects',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS deleteExpiredDualOptInUserObjects(): Deletes expired user accounts caused by unconfirmed registration links in "dual opt in" registration method'
        );


        $this->server->register(
            'readWebLink',
            array('sid' => 'xsd:string', "ref_id" => 'xsd:int'),
            array('weblinkxml' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#readWebLink',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS readWebLink(): returns xml description of a weblink container.'
        );

        $this->server->register(
            'createWebLink',
            array('sid' => 'xsd:string', "target_id" => 'xsd:int', "xml" => "xsd:string"),
            array('refid' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#createWebLink',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS createWebLink(): create web link container, put it into target (ref_id) and update weblink container from xml (see ilias_weblink_4_0.dtd for details). Obj_id must not be set!'
        );

        $this->server->register(
            'updateWebLink',
            array('sid' => 'xsd:string', 'ref_id' => 'xsd:int', 'xml' => 'xsd:string'),
            array('success' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#updateWebLink',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS updateWebLink():update existing weblink, update weblink properties from xml (see ilias_weblink_4_0.dtd for details).'
        );
        
        // mcs-patch start
        $this->server->register(
            'getLearningProgressChanges',
            array('sid' => 'xsd:string', 'timestamp' => 'xsd:string', 'include_ref_ids' => 'xsd:boolean', 'type_filter' => 'tns:stringArray'),
            array('lp_data' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getLearningProgressChanges',
            SERVICE_STYLE,
            SERVICE_USE,
            'ILIAS getLearningProgressChanges(): Get learning progress changes after a given timestamp.'
        );
        // mcs-patch end
        


        // fau: soapFunctions - add new soap functions
        $this->server->wsdl->addComplexType(
            'studonResource',
            'complexType',
            'struct',
            'all',
            '',
            array('univis_id' => array('name' => 'univis_id',
                                                                    'type' => 'xsd:string'),
                                                  'perma_link' => array('name' => 'perma_link',
                                                                       'type' => 'xsd:string'))
        );
        $this->server->wsdl->addComplexType(
            'studonResources',
            'complexType',
            'array',
            '',
            'SOAP-ENC:Array',
            array(),
            array(array('ref' => 'SOAP-ENC:arrayType',
                                                        'wsdl:arrayType' => 'tns:studonResource[]')),
            'tns:studonResource'
        );


        $this->server->register(
            'studonGetResources',
            array('sid' => 'xsd:string', 'semester' => 'xsd:string'),
            array('result' => 'tns:studonResources'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonGetResources',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonGetResources(): returns a list of semester resources'
        );
                                
                                
        $this->server->register(
            'studonHasResource',
            array('sid' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonHasResource',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonHasResource(): returns true, if an object is found for the given univis_id'
        );

        $this->server->register(
            'studonGetPermaLink',
            array('sid' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonGetPermaLink',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonGetPermaLink(): returns the url for a permanent link to the resource'
        );

        $this->server->register(
            'studonGetMembers',
            array('sid' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'tns:stringArray'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonGetMembers',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonGetMembers(): returns the identities of course participants'
        );

        $this->server->register(
            'studonIsSoapAssignable',
            array('sid' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonIsSoapAssignable',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonIsSoapAssignable(): return true if user assignment can be done by SOAP'
        );

        $this->server->register(
            'studonIsAssigned',
            array('sid' => 'xsd:string', 'identity' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonIsAssigned',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonIsAssigned(): checks if a user is assigned to a course or group by given univis_id'
        );


        $this->server->register(
            'studonAssignMember',
            array('sid' => 'xsd:string', 'identity' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonAssignMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonAssignMember(): assigns a user to a course or group by given univis_id'
        );

        $this->server->register(
            'studonExcludeMember',
            array('sid' => 'xsd:string', 'identity' => 'xsd:string', 'univis_id' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonExcludeMember',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonExcludeMember(): excludes a user from a course or group by given univis_id'
        );

        // functions for TCA

        $this->server->wsdl->addComplexType(
            'studonLtiCredentials',
            'complexType',
            'struct',
            'all',
            '',
            array('consumerKey' => array('name' => 'consumerKey',
                                       'type' => 'xsd:string'),
                  'consumerSecret' => array('name' => 'consumerSecret',
                                        'type' => 'xsd:string'))
        );


        $this->server->register(
            'studonCopyCourse',
            array('sid' => 'xsd:string', 'sourceRefId' => 'xsd:int', 'targetRefId' => 'xsd:int',
                'typesToLink' => 'tns:stringArray'),
            array('result' => 'xsd:int'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonCopyCourse',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonCopyCourse(): copies a course.
                sid: session id;
                sourceRefId: ref_id of the course to be copied;
                targetRefId: ref_id of the place where copied course should be added;
                typesToLink: types of course contents which should be linked instead of copied;
                returns the ref_id of the copied course
            '
        );

        $this->server->register(
            'studonSetCourseProperties',
            array('sid' => 'xsd:string', 'refId' => 'xsd:int',
                  'title' => 'xsd:string', 'description' => 'xsd:string', 'online'=> 'xsd:boolean',
                  'courseStart' => 'xsd:int', 'courseEnd' => 'xsd:int',
                  'activationStart' => 'xsd:int', 'activationEnd' => 'xsd:int'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonSetCourseProperties',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonSetCourseProperties(): sets the basic properties of a course.
                sid: session id;
                refId: ref_id of the course to be changed;
                title: new course title;
                description: new course description (shown below the title);
                online: course should be visible to the members;
                courseStart: new course start (unix timestamp, only day will be used);
                courseEnd: new course end (unix timestamp, only day will be used);
                activationStart: new start of availability (unix timestamp);
                activationEnd: new end of availability (unix timestamp);
                returns true in case of success
            '
        );

        $this->server->register(
            'studonSetCourseInfo',
            array('sid' => 'xsd:string', 'refId' => 'xsd:int',
                  'importantInformation' => 'xsd:string', 'syllabus' => 'xsd:string', 'contactName'=> 'xsd:string',
                  'contactResponsibility' => 'xsd:string', 'contactPhone' => 'xsd:string',
                  'contactEmail' => 'xsd:string', 'contactConsultation' => 'xsd:string'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonSetCourseInfo',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonSetCourseInfo(): sets the data shown on the info page of a course.
                sid: session id;
                refId: ref_id of the course to be changed;
                importantInformation: important information for course participants;
                syllabus: description of the course contents, learning ourcomes etc.;
                contactName: name of the organizational contact person;
                contactResponsibility: responsibility of the contect person;
                contactPhone: phone number of the contact person;
                contactEmail: email address of the contact person;
                contactConsultation: consultation hours of the contact person;
                returns true in case of success
            '
        );


        $this->server->register(
            'studonAddCourseAdminsByIdentity',
            array('sid' => 'xsd:string', 'refId' => 'xsd:int',
                  'admins' => 'tns:stringArray'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonAddCourseAdminsByIdentity',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonAddCourseAdminsByIdentity(): add course administrators with their idm accounts.
                Dummy accounts will be created if the users do not yet exist.
                Existing administrators will be kept.
                sid: session id;
                refId: ref_id of the course;
                admins: idm identities of users that should be added as course admins;
                returns true in case of success
            '
        );

        $this->server->register(
            'studonSetCourseAdminsByIdentity',
            array('sid' => 'xsd:string', 'refId' => 'xsd:int',
                  'admins' => 'tns:stringArray'),
            array('result' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonSetCourseAdminsByIdentity',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonSetCourseAdminsByIdentity(): sets course administrators with their idm accounts.
                Dummy accounts will be created if the users do not yet exist.
                Existing administrators will be removed if they do not match.
                sid: session id;
                refId: ref_id of the course;
                admins: idm identities of users that should be set as course admins;
                returns true in case of success
            '
        );


        $this->server->register(
            'studonEnableLtiConsumer',
            array('sid' => 'xsd:string', 'refId' => 'xsd:int', 'consumerId' => 'xsd:int',
                  'adminRole' => 'xsd:string', 'instructorRole' => 'xsd:string',  'memberRole' => 'xsd:string'),
            array('result' => 'tns:studonLtiCredentials'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#studonEnableLtiConsumer',
            SERVICE_STYLE,
            SERVICE_USE,
            'studonEnableLtiConsumer(): enables an LTI consumer for a course.
                sid: session id;
                refId: ref_id of the course; 
                consumerId: id of the lti consumer configuration in studon
                adminRole: studon course role of lti admins (admin|tutor|member);
                instructorRole: studon course role of lti instructors (admin|tutor|member);
                memberRole: studon course role of lti members (admin|tutor|member);
                returns: lti credentials [consumerKey, consumerSecret]
            '
        );


        // fau.

                                    
        $this->server->register(
            'deleteProgress',
            array(
                    'sid' => 'xsd:string',
                    'ref_ids' => 'tns:intArray',
                    'usr_ids' => 'tns:intArray',
                    'type_filter' => 'tns:stringArray',
                    'progress_filter' => 'tns:intArray'
                ),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#deleteProgress',
            SERVICE_STYLE,
            SERVICE_USE,
            'Delete user progress data of objects. '
            );
        
        
        $this->server->register(
            'getProgressInfo',
            array(
                'sid' => 'xsd:string',
                'ref_id' => 'xsd:int',
                'progress_filter' => 'tns:intArray'
            ),
            array('user_results' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#getProgressInfo',
            SERVICE_STYLE,
            SERVICE_USE,
            'Get object learning progress information'
        );


        $this->server->register(
            'exportDataCollectionContent',
            array(
                'sid' => 'xsd:string',
                'ref_id' => 'xsd:int',
                'table_id' => 'xsd:int',
                'format' => 'xsd:string',
                'filepath' => 'xsd:string'
            ),
            array('export_path' => 'xsd:string'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#exportDataCollectionTableContent',
            SERVICE_STYLE,
            SERVICE_USE,
            'Generate DataCollectionContent Export'
        );
        
        $this->server->register(
            'processBackgroundTask',
            array(
                'sid' => 'xsd:string',
                'task_id' => 'xsd:int'
            ),
            array('status' => 'xsd:boolean'),
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#processBackgroundTask',
            SERVICE_STYLE,
            SERVICE_USE,
            'Process task in background'
        );

        $this->server->register(
            'addDesktopItems',
            [
                'sid' => 'xsd:string',
                'user_id' => 'xsd:int',
                'reference_ids' => 'tns:intArray'
            ],
            [
                'num_added' => 'xsd:int'
            ],
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#addDesktopItems',
            SERVICE_STYLE,
            SERVICE_USE,
            'Add desktop items for user'
        );

        $this->server->register(
            'removeDesktopItems',
            [
                'sid' => 'xsd:string',
                'user_id' => 'xsd:int',
                'reference_ids' => 'tns:intArray'
            ],
            [
                'num_added' => 'xsd:int'
            ],
            SERVICE_NAMESPACE,
            SERVICE_NAMESPACE . '#removeDesktopItems',
            SERVICE_STYLE,
            SERVICE_USE,
            'Remove desktop items for user'
        );

        // OrgUnits Functions
        /**
         * @var $f Base[]
         */
        $f = [
            new AddUserIdToPositionInOrgUnit(),
            new EmployeePositionId(),
            new ImportOrgUnitTree(),
            new OrgUnitTree(),
            new PositionIds(),
            new PositionTitle(),
            new RemoveUserIdFromPositionInOrgUnit(),
            new SuperiorPositionId(),
            new UserIdsOfPosition(),
            new UserIdsOfPositionAndOrgUnit()
        ];

        foreach ($f as $function) {
            $this->server->register(
                $function->getName(),
                $function->getInputParams(),
                $function->getOutputParams(),
                SERVICE_NAMESPACE,
                SERVICE_NAMESPACE . '#orgu',
                SERVICE_STYLE,
                SERVICE_USE,
                $function->getDocumentation()
            );
        }

        // If a client ID is submitted, there might be some SOAP plugins registering methods/types
        if (isset($_GET['client_id'])) {
            $this->handleSoapPlugins();
        }

        return true;
    }

    /**
     * Register any methods and types of SOAP plugins to the SOAP server
     */
    protected function handleSoapPlugins()
    {
        // Note: We need a context that does not handle authentication at this point, because this is
        // handled by an actual SOAP request which always contains the session ID and client
        ilContext::init(ilContext::CONTEXT_SOAP_NO_AUTH);
        ilInitialisation::initILIAS();
        ilContext::init(ilContext::CONTEXT_SOAP);

        global $DIC;

        $ilPluginAdmin = $DIC['ilPluginAdmin'];
        $soapHook = new ilSoapHook($ilPluginAdmin);
        foreach ($soapHook->getWsdlTypes() as $type) {
            $this->server->wsdl->addComplexType(
                $type->getName(),
                $type->getTypeClass(),
                $type->getPhpType(),
                $type->getCompositor(),
                $type->getRestrictionBase(),
                $type->getElements(),
                $type->getAttributes(),
                $type->getArrayType()
            );
        }
        foreach ($soapHook->getSoapMethods() as $method) {
            $this->server->register(
                $method->getName(),
                $method->getInputParams(),
                $method->getOutputParams(),
                $method->getServiceNamespace(),
                $method->getServiceNamespace() . '#' . $method->getName(),
                $method->getServiceStyle(),
                $method->getServiceUse(),
                $method->getDocumentation()
            );
        }
    }
}
