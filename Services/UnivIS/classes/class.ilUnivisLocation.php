<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once('./Services/UnivIS/classes/class.ilUnivisData.php');

/**
*  fau: univisImport - class for looking up location data.
*/
class ilUnivisLocation extends ilUnivisData
{
    /**
    * Read the data (to be overwritten)
    *
    * @param 	string     string representation of the primary key
    */
    public function read($a_primary_key)
    {
        global $ilDB;

        $query = "SELECT * FROM univis_person_location "
                . " WHERE " . parent::_getLookupCondition()
                . " AND " . parent::_getPrimaryCondition('univis_person_location', $a_primary_key);

        $result = $ilDB->query($query);
        if ($row = $ilDB->fetchAssoc($result)) {
            $this->data = $row;
        } else {
            $this->data = array();
        }
    }


    /**
    * Get the primary key
    * The primary key should be extracted from the data
    *
    * @return 	string     string representation of the primary key
    */
    public function getPrimaryKey()
    {
        return parent::_getPrimaryKey('univis_person_location', $this->data);
    }

    /**
    * Get the phone number
    */
    public function getPhone()
    {
        return $this->data['tel'];
    }

    /**
    * Get the email address
    */
    public function getEmail()
    {
        return $this->data['email'];
    }



    /**
    * Get all locations of a person
    *
    * @param 	string      lperson key
    * @return   array       list of location objects
    */
    public static function _getLocationsOfPerson($a_person_key)
    {
        global $ilDB;

        $locations = array();

        $query = "SELECT * FROM univis_person_location "
                . " WHERE " . parent::_getLookupCondition()
                . " AND person_key = " . $ilDB->quote($a_person_key, 'text')
                . " ORDER BY orderindex";

        $result = $ilDB->query($query);
        while ($row = $ilDB->fetchAssoc($result)) {
            $location = new ilUnivisLocation();
            $location->setData($row);
            $locations[] = $location;
        }

        return $locations;
    }
}
