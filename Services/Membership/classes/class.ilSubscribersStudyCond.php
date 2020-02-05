<?php
/* fim: [memcond] new class. */

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* class for studydata based subscription conditions
* 
* @author Fred Neumann <fred.neumann@fim.uni-erlangen.de>
* @version $Id$
*
* @ingroup ServicesMembership
*/

class ilSubscribersStudyCond
{
	private $cond_id;
	private $obj_id;
	private $subject_id;
	private $degree_id;
	private $min_semester;
	private $max_semester;
	private $ref_semester;
	private $study_type;


	/**
	 * Constructor
	 *
	 * @access public
	 * @param int obj_id 
	 */
	public function __construct($a_cond_id = null)
	{
		if ($this->cond_id = $a_cond_id)
		{
			$this->read();
		}
	}
	
	/**
	 * Getters / Setters
	 */
	public function getCondId()
	{
		return $this->cond_id;
	}
	public function setCondId($a_id)
	{
		$this->cond_id = $a_id;
	}

	public function getObjId()
	{
		return $this->obj_id;
	}
	public function setObjId($a_id)
	{
		$this->obj_id = $a_id;
	}

	public function getSubjectId()
	{
		return $this->subject_id;
	}
	public function setSubjectId($a_subject_id)
	{
		$this->subject_id = $a_subject_id;
	}

	public function getDegreeId()
	{
		return $this->degree_id;
	}
	public function setDegreeId($a_degree_id)
	{
		$this->degree_id = $a_degree_id;
	}

	public function getMinSemester()
	{
		return $this->min_semester;
	}
	public function setMinSemester($a_semester)
	{
		$this->min_semester = $a_semester;
	}

	public function getMaxSemester()
	{
		return $this->max_semester;
	}
	public function setMaxSemester($a_semester)
	{
		$this->max_semester = $a_semester;
	}

	public function getRefSemester()
	{
		return $this->ref_semester;
	}
	public function setRefSemester($a_semester)
	{
		$this->ref_semester = $a_semester;
	}
	public function getStudyType()
	{
		return $this->study_type;
	}
	public function setStudyType($study_type)
	{
		if (empty($study_type)) {
			$this->study_type = null;
		}
		else {
			$this->study_type = $study_type;
		}
	}


	/**
	 * Read a condition from the database
	 */
	public function read()
	{
		global $DIC;
		$ilDB = $DIC->database();
		
		$query = "SELECT * FROM il_sub_studycond ".
			"WHERE cond_id = ".$ilDB->quote($this->cond_id, 'integer');

		$result = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($result))
		{
			$this->setRowData($row);
			return true;
		}
		return false;
	}
	
	/**
	 * create a new condition in the database
	 */
	public function create()
	{
		global $DIC;
		$ilDB = $DIC->database();

		$cond_id = $ilDB->nextId('il_sub_studycond');
		
		$query = "INSERT INTO il_sub_studycond ("
		        ."cond_id, obj_id, subject_id, degree_id, max_semester, min_semester, ref_semester, study_type) "
		        ."VALUES("
		        .$ilDB->quote($cond_id, 'integer').", "
		        .$ilDB->quote($this->obj_id, 'integer') .", "
		        .$ilDB->quote($this->subject_id, 'integer') .", "
		        .$ilDB->quote($this->degree_id, 'integer') .", "
		        .$ilDB->quote($this->max_semester, 'integer') .", "
		        .$ilDB->quote($this->min_semester, 'integer') .", " 
		        .$ilDB->quote($this->ref_semester, 'text') .", "
				.$ilDB->quote($this->study_type, 'text') .")";
      	$ilDB->manipulate($query);

		$this->cond_id = $cond_id;
		return true;
	}

	
	/**
	 * update the current condition in the database
	 */
	public function update()
	{
		global $DIC;
		$ilDB = $DIC->database();

		$query = "UPDATE il_sub_studycond SET "
		        ."obj_id = ".$ilDB->quote($this->obj_id, 'integer') .", "
				."subject_id = " .$ilDB->quote($this->subject_id, 'integer') .", "
				."degree_id = " .$ilDB->quote($this->degree_id, 'integer') .", "
				."max_semester = " .$ilDB->quote($this->max_semester, 'integer') .", "
				."min_semester = " .$ilDB->quote($this->min_semester, 'integer') .", "
				."ref_semester = " .$ilDB->quote($this->ref_semester, 'text') .", "
				."study_type = " .$ilDB->quote($this->study_type, 'text') ." "
				."WHERE cond_id = ".$ilDB->quote($this->cond_id, 'integer');
				
      	$ilDB->manipulate($query);
      	
      	return true;
	}

	/**
	 * update the current condition
	 */
	public function delete()
	{
		global $DIC;
		$ilDB = $DIC->database();

		$query = "DELETE FROM il_sub_studycond WHERE cond_id = ".$ilDB->quote($this->cond_id, 'integer');
		$ilDB->manipulate($query);

		return true;
	}


	/**
	 * set the data from an assoc array
	 *
	 * @access private
	 * @param   array   assoc array of data
	 */
	private function setRowData($a_row = array())
	{
		$this->setCondId($a_row['cond_id']);
		$this->setObjId($a_row['obj_id']);
		$this->setSubjectId($a_row['subject_id']);
		$this->setDegreeId($a_row['degree_id']);
		$this->setMinSemester($a_row['min_semester']);
		$this->setMaxSemester($a_row['max_semester']);
		$this->setRefSemester($a_row['ref_semester']);
		$this->setStudyType($a_row['study_type']);
	}

	/**
	 * check if an object has conditions defined
	 *
	 * @param 	int 		obj_id
	 * @return   boolean    conditions exist
	 */
	public static function _hasConditions($a_obj_id)
	{
		global $DIC;
		$ilDB = $DIC->database();

		$query = "SELECT cond_id FROM il_sub_studycond ".
		"WHERE obj_id = ".$ilDB->quote($a_obj_id, 'integer');
		$result = $ilDB->query($query);
		if ($row = $ilDB->fetchAssoc($result))
		{
			return true;
		}
		return false;
	}
	

	/**
	 * get all condition data for an object
	 *
	 * @param 	int 		obj_id
	 * @return  array   	assoc array of rows
	 */
	public static function _getConditionsData($a_obj_id)
	{
		global $DIC;
		$ilDB = $DIC->database();

		$query = "SELECT * FROM il_sub_studycond ".
		"WHERE obj_id = ".$ilDB->quote($a_obj_id, 'integer');
		$result = $ilDB->query($query);

		$data = array();
		while ($row = $ilDB->fetchAssoc($result))
		{
			$data[] = $row;
		}
		return $data;
	}
	

	/**
	 * get all condition objects for an object
	 *
	 * @param 	int 		obj_id
	 * @param   array   	assoc array of condition objects
	 * @return array
	 */
	public static function _getConditions($a_obj_id)
	{
		$conditions = array();
		$data = self:: _getConditionsData($a_obj_id);
		
		foreach ($data as $row)
		{
			$cond = new ilSubscribersStudyCond();
			$cond->setRowData($row);
			$conditions[] = $cond;
		}
		return $conditions;
	}
	
	
	/**
	 * get the textual version of all conditions for an object
	 *
	 * @param 	int 		$a_obj_id
	 * @return   string   	conditions text
	 */
	public static function _getConditionsText($a_obj_id)
	{
		global $DIC;
		$lng = $DIC->language();
		
		require_once('Services/StudyData/classes/class.ilStudyData.php');
        require_once('Services/StudyData/classes/class.ilStudyOptionDegree.php');
        require_once('Services/StudyData/classes/class.ilStudyOptionSubject.php');

		$text = array();
		$data = self::_getConditionsData($a_obj_id);
		foreach ($data as $cond)
		{
			$reftext = ilStudyData::_getRefSemesterText($cond['ref_semester']);
			$type_text =  ilStudyData::_getStudyTypeText($cond['study_type']);
			$ctext = array();

			if ($cond['subject_id'])
			{
				$ctext[] = ilStudyOptionSubject::_lookupText($cond['subject_id']);
			}
			if ($cond['degree_id'])
			{
				$ctext[] = ilStudyOptionDegree::_lookupText($cond['degree_id']);
			}
			if ($cond['min_semester'] and $cond['max_semester'])
			{
				$ctext[] = sprintf($lng->txt('studycond_min_max_semester'), $cond['min_semester'], $cond['max_semester'], $reftext);
			}
			elseif ($cond['min_semester'])
			{
				$ctext[] = sprintf($lng->txt('studycond_min_semester'), $cond['min_semester'], $reftext);
			}
			elseif ($cond['max_semester'])
			{
				$ctext[] = sprintf($lng->txt('studycond_max_semester'), $cond['max_semester'], $reftext);
			}
			if ($type_text)
			{
				$ctext[] = $type_text;
			}

			if (count($ctext))
			{
				$text[] = implode($lng->txt('studycond_criteria_delimiter').' ', $ctext);
			}
		}
		
		if (count($text))
		{
			return implode($lng->txt('studycond_condition_delimiter').' ', $text);
		}
		else
		{
			return $lng->txt('studycond_no_condition_defined');
		}
	}

	/**
	 * Clone the studydata conditions from one object to another
	 * @param $old_obj_id
	 * @param $new_obj_id
	 */
	public static function _clone($old_obj_id, $new_obj_id)
	{
		foreach (self::_getConditionsData($old_obj_id) as $row)
		{
			$condition = new ilSubscribersStudyCond();
			$condition->setRowData($row);
			$condition->setObjId($new_obj_id);
			$condition->create();
		}
	}
	
	/**
	 * Check the studydata conditions for a user and an object
	 *
	 * @access 	public
	 * @param 	int 		obj_id
	 * @param 	int 		user_id
	 * @return 	boolean 	conditions are met (true/false)
	 * @static
	 */
	public static function _checkConditions($a_obj_id, $a_user_id)
	{
		$conditionsdata = self::_getConditionsData($a_obj_id);
		if (!count($conditionsdata))
		{
			return true;
		}
		$studydata = ilStudyAccess::_getStudyData($a_user_id);
		if (!count($studydata))
		{
			return false;
		}

		return ilStudyAccess::_checkConditions($conditionsdata, $studydata);
	}
	
	
	/**
	 * delete all condition data of an object
	 *
	 * @access public
	 * @param int obj_id
	 * @static
	 * @return bool
	 */
	public static function _deleteAll($a_obj_id)
	{
		global $DIC;
		$ilDB = $DIC->database();

		$query = "DELETE FROM il_sub_studycond WHERE obj_id = ".$ilDB->quote($a_obj_id, 'integer');
		$ilDB->query($query);

		return true;
	}
}
