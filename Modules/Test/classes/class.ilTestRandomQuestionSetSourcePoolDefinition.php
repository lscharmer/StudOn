<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package		Modules/Test
 */
class ilTestRandomQuestionSetSourcePoolDefinition
{
	/**
	 * global $ilDB object instance
	 *
	 * @var ilDB
	 */
	protected $db = null;
	
	/**
	 * object instance of current test
	 *
	 * @var ilObjTest
	 */
	protected $testOBJ = null;

    private $id = null;
	
	private $poolId = null;
	
	private $poolTitle = null;
	
	private $poolPath = null;
	
	private $poolQuestionCount = null;

//  fau: taxFilter - deactivate ols class variables
//	private $originalFilterTaxId = null;
//
//	private $originalFilterTaxNodeId = null;
//
//	private $mappedFilterTaxId = null;
//
//	private $mappedFilterTaxNodeId = null;
//  fau.

	private $questionAmount = null;
	
	private $sequencePosition = null;

// fau: taxFilter - new class variables
	/**
	 * @var array taxId => [nodeId, ...]
	 */
	private $originalTaxonomyFilter = array();

	/**
	 * @var array taxId => [nodeId, ...]
	 */
	private $mappedTaxonomyFilter = array();
// fau.

// fau: taxGroupFilter - new class variables
	private $originalGroupTaxId = null;
	private $mappedGroupTaxId = null;
// fau.

// fau: typeFilter - new class variable
	private $typeFilter = array();
// fau.

// fau: randomSetOrder - new class variable
	private $orderBy = null;
// fau.
	
	public function __construct(ilDB $db, ilObjTest $testOBJ)
	{
		$this->db = $db;
		$this->testOBJ = $testOBJ;
	}

    public function setId($id)
	{
		$this->id = $id;
	}

	public function getId()
	{
		return $this->id;
	}
	
	public function setPoolId($poolId)
	{
		$this->poolId = $poolId;
	}
	
	public function getPoolId()
	{
		return $this->poolId;
	}
	
	public function setPoolTitle($poolTitle)
	{
		$this->poolTitle = $poolTitle;
	}
	
	public function getPoolTitle()
	{
		return $this->poolTitle;
	}
	
	public function setPoolPath($poolPath)
	{
		$this->poolPath = $poolPath;
	}
	
	public function getPoolPath()
	{
		return $this->poolPath;
	}
	
	public function setPoolQuestionCount($poolQuestionCount)
	{
		$this->poolQuestionCount = $poolQuestionCount;
	}
	
	public function getPoolQuestionCount()
	{
		return $this->poolQuestionCount;
	}

// fau: taxFilter - hide the functions for selecting single taxonomies and nodes

//	public function setOriginalFilterTaxId($originalFilterTaxId)
//	{
//		$this->originalFilterTaxId = $originalFilterTaxId;
//	}
//
//	public function getOriginalFilterTaxId()
//	{
//		return $this->originalFilterTaxId;
//	}
//
//	public function setOriginalFilterTaxNodeId($originalFilterNodeId)
//	{
//		$this->originalFilterTaxNodeId = $originalFilterNodeId;
//	}
//
//	public function getOriginalFilterTaxNodeId()
//	{
//		return $this->originalFilterTaxNodeId;
//	}
//
//	public function setMappedFilterTaxId($mappedFilterTaxId)
//	{
//		$this->mappedFilterTaxId = $mappedFilterTaxId;
//	}
//
//	public function getMappedFilterTaxId()
//	{
//		return $this->mappedFilterTaxId;
//	}
//
//	public function setMappedFilterTaxNodeId($mappedFilterTaxNodeId)
//	{
//		$this->mappedFilterTaxNodeId = $mappedFilterTaxNodeId;
//	}
//
//	public function getMappedFilterTaxNodeId()
//	{
//		return $this->mappedFilterTaxNodeId;
//	}

// fau.

// fau: taxFilter - new setters/getters for taxomony filters

	/**
	 * get the original taxonomy filter conditions
	 * @return array	taxId => [nodeId, ...]
	 */
	public function getOriginalTaxonomyFilter()
	{
		return $this->originalTaxonomyFilter;
	}

	/**
	 * set the original taxonomy filter condition
	 * @param  array taxId => [nodeId, ...]
	 */
	public function setOriginalTaxonomyFilter($filter = array())
	{
			$this->originalTaxonomyFilter = $filter;
	}

	/**
	 * get the original taxonomy filter for insert into the database
	 * @return null|string		serialized taxonomy filter
	 */
	private function getOriginalTaxonomyFilterForDbValue()
	{
		return empty($this->originalTaxonomyFilter) ? null : serialize($this->originalTaxonomyFilter);
	}

	/**
	 * get the original taxonomy filter from database value
	 * @param null|string		serialized taxonomy filter
	 */
	private function setOriginalTaxonomyFilterFromDbValue($value)
	{
		$this->originalTaxonomyFilter = empty($value) ? array() : unserialize($value);
	}

	/**
	 * get the mapped taxonomy filter conditions
	 * @return 	array	taxId => [nodeId, ...]
	 */
	public function getMappedTaxonomyFilter()
	{
		return $this->mappedTaxonomyFilter;
	}

	/**
	 * set the original taxonomy filter condition
	 * @param array 	taxId => [nodeId, ...]
	 */
	public function setMappedTaxonomyFilter($filter = array())
	{
		$this->mappedTaxonomyFilter = $filter;
	}

	/**
	 * get the original taxonomy filter for insert into the database
	 * @return null|string		serialized taxonomy filter
	 */
	private function getMappedTaxonomyFilterForDbValue()
	{
		return empty($this->mappedTaxonomyFilter) ? null : serialize($this->mappedTaxonomyFilter);
	}

	/**
	 * get the original taxonomy filter from database value
	 * @param null|string		serialized taxonomy filter
	 */
	private function setMappedTaxonomyFilterFromDbValue($value)
	{
		$this->mappedTaxonomyFilter = empty($value) ? array() : unserialize($value);
	}


	/**
	 * set the mapped taxonomy filter from original by applying a keys map
	 * @param ilQuestionPoolDuplicatedTaxonomiesKeysMap $taxonomiesKeysMap
	 */
	public function mapTaxonomyFilter(ilQuestionPoolDuplicatedTaxonomiesKeysMap $taxonomiesKeysMap)
	{
		$this->mappedTaxonomyFilter = array();
		foreach ($this->originalTaxonomyFilter as $taxId => $nodeIds)
		{
			$mappedNodeIds = array();
			foreach ($nodeIds as $nodeId)
			{
				$mappedNodeIds[] = $taxonomiesKeysMap->getMappedTaxNodeId($nodeId);
			}
			$this->mappedTaxonomyFilter[$taxonomiesKeysMap->getMappedTaxonomyId($taxId)] = $mappedNodeIds;
		}
	}

// fau.


// fau: taxGroupFilter - setters and getters
	public function setOriginalGroupTaxId($originalGroupTaxId)
	{
		$this->originalGroupTaxId = $originalGroupTaxId;
	}

	public function getOriginalGroupTaxId()
	{
		return $this->originalGroupTaxId;
	}

	public function setMappedGroupTaxId($mappedGroupTaxId)
	{
		$this->mappedGroupTaxId = $mappedGroupTaxId;
	}

	public function getMappedGroupTaxId()
	{
		return $this->mappedGroupTaxId;
	}
// fau.


// fau: typeFilter - setters and getters
	public function setTypeFilter($typeFilter = array())
	{
		$this->typeFilter = $typeFilter;
	}

	public function getTypeFilter()
	{
		return $this->typeFilter;
	}

	/**
	 * get the question type filter for insert into the database
	 * @return null|string		serialized type filter
	 */
	private function getTypeFilterForDbValue()
	{
		return empty($this->typeFilter) ? null : serialize($this->typeFilter);
	}

	/**
	 * get the question type filter from database value
	 * @param null|string		serialized type filter
	 */
	private function setTypeFilterFromDbValue($value)
	{
		$this->typeFilter = empty($value) ? array() : unserialize($value);
	}
// fau.

// fau: randomSetOrder - setters and getters

	/**
	 * Set the field to ordder a random set
	 * @param string|null $orderBy		'title', 'description', 'random'
	 */
	public function setOrderBy($orderBy)
	{
		$this->orderBy = $orderBy;
	}

	/**
	 * Set the field to ordder a random set
	 * @return string|null 		'title' or 'description'
	 */
	public function getOrderBy()
	{
		return $this->orderBy;
	}
// fau.


	public function setQuestionAmount($questionAmount)
	{
		$this->questionAmount = $questionAmount;
	}
	
	public function getQuestionAmount()
	{
		return $this->questionAmount;
	}
	
	public function setSequencePosition($sequencePosition)
	{
		$this->sequencePosition = $sequencePosition;
	}
	
	public function getSequencePosition()
	{
		return $this->sequencePosition;
	}
	
	// -----------------------------------------------------------------------------------------------------------------
	
	/**
	 * @param array $dataArray
	 */
	public function initFromArray($dataArray)
	{
		foreach($dataArray as $field => $value)
		{
			switch($field)
			{
				case 'def_id':				$this->setId($value);						break;
				case 'pool_fi':				$this->setPoolId($value);					break;
				case 'pool_title':			$this->setPoolTitle($value);				break;
				case 'pool_path':			$this->setPoolPath($value);					break;
				case 'pool_quest_count':	$this->setPoolQuestionCount($value);		break;
// fau: taxFilter - use new db fields
//				case 'origin_tax_fi':		$this->setOriginalFilterTaxId($value);		break;
//				case 'origin_node_fi':		$this->setOriginalFilterTaxNodeId($value);	break;
//				case 'mapped_tax_fi':		$this->setMappedFilterTaxId($value);		break;
//				case 'mapped_node_fi':		$this->setMappedFilterTaxNodeId($value);	break;
				case 'origin_tax_filter':	$this->setOriginalTaxonomyFilterFromDbValue($value);	break;
				case 'mapped_tax_filter':	$this->setMappedTaxonomyFilterFromDbValue($value);		break;
// fau: taxGroupFilter - read from db
				case 'origin_group_tax_fi':	$this->setOriginalGroupTaxId($value);	break;
				case 'mapped_group_tax_fi': $this->setMappedGroupTaxId(($value));	break;
// fau.
// fau.
// fau: typeFilter - read from db
				case 'type_filter':			$this->setTypeFilterFromDbValue($value);	break;
// fau.
// fau: randomSetOrder - read from db
				case 'order_by':			$this->setOrderBy($value);		break;
// fau.
				case 'quest_amount':		$this->setQuestionAmount($value);			break;
				case 'sequence_pos':		$this->setSequencePosition($value);			break;
			}
		}
	}
	
	/**
	 * @param integer $poolId
	 * @return boolean
	 */
	public function loadFromDb($id)
	{
		$res = $this->db->queryF(
				"SELECT * FROM tst_rnd_quest_set_qpls WHERE def_id = %s", array('integer'), array($id)
		);
		
		while( $row = $this->db->fetchAssoc($res) )
		{
			$this->initFromArray($row);
			
			return true;
		}
		
		return false;
	}

	public function saveToDb()
	{
		if( $this->getId() )
		{
			$this->updateDbRecord($this->testOBJ->getTestId());
		}
		else
		{
			$this->insertDbRecord($this->testOBJ->getTestId());
		}
	}

	public function cloneToDbForTestId($testId)
	{
		$this->insertDbRecord($testId);
	}

	public function deleteFromDb()
	{
		$this->db->manipulateF(
				"DELETE FROM tst_rnd_quest_set_qpls WHERE def_id = %s", array('integer'), array($this->getId())
		);
	}

	/**
	 * @param $testId
	 */
	private function updateDbRecord($testId)
	{
		$this->db->update('tst_rnd_quest_set_qpls',
			array(
				'test_fi' => array('integer', $testId),
				'pool_fi' => array('integer', $this->getPoolId()),
				'pool_title' => array('text', $this->getPoolTitle()),
				'pool_path' => array('text', $this->getPoolPath()),
				'pool_quest_count' => array('integer', $this->getPoolQuestionCount()),
// fau: taxFilter - use new db fields
//				'origin_tax_fi' => array('integer', $this->getOriginalFilterTaxId()),
//				'origin_node_fi' => array('integer', $this->getOriginalFilterTaxNodeId()),
//				'mapped_tax_fi' => array('integer', $this->getMappedFilterTaxId()),
//				'mapped_node_fi' => array('integer', $this->getMappedFilterTaxNodeId()),
				'origin_tax_filter' => array('text', $this->getOriginalTaxonomyFilterForDbValue()),
				'mapped_tax_filter' => array('text', $this->getMappedTaxonomyFilterForDbValue()),
// fau.
// fau: taxGroupFilter - update in db
				'origin_group_tax_fi' => array('integer', $this->getOriginalGroupTaxId()),
				'mapped_group_tax_fi' => array('integer', $this->getMappedGroupTaxId()),
// fau.
// fau: typeFilter - update in db
				'type_filter' => array('text', $this->getTypeFilterForDbValue()),
// fau.
// fau: randomSetOrder - update in db
				'order_by' => array('text', $this->getOrderBy()),
// fau.
				'quest_amount' => array('integer', $this->getQuestionAmount()),
				'sequence_pos' => array('integer', $this->getSequencePosition())
			),
			array(
				'def_id' => array('integer', $this->getId())
			)
		);
	}

	/**
	 * @param $testId
	 */
	private function insertDbRecord($testId)
	{
		$nextId = $this->db->nextId('tst_rnd_quest_set_qpls');

		$this->db->insert('tst_rnd_quest_set_qpls', array(
				'def_id' => array('integer', $nextId),
				'test_fi' => array('integer', $testId),
				'pool_fi' => array('integer', $this->getPoolId()),
				'pool_title' => array('text', $this->getPoolTitle()),
				'pool_path' => array('text', $this->getPoolPath()),
				'pool_quest_count' => array('integer', $this->getPoolQuestionCount()),
// fau: taxFilter - use new db fields
//				'origin_tax_fi' => array('integer', $this->getOriginalFilterTaxId()),
//				'origin_node_fi' => array('integer', $this->getOriginalFilterTaxNodeId()),
//				'mapped_tax_fi' => array('integer', $this->getMappedFilterTaxId()),
//				'mapped_node_fi' => array('integer', $this->getMappedFilterTaxNodeId()),
				'origin_tax_filter' => array('text', $this->getOriginalTaxonomyFilterForDbValue()),
				'mapped_tax_filter' => array('text', $this->getMappedTaxonomyFilterForDbValue()),
// fau.
// fau: taxGroupFilter - insert in db
				'origin_group_tax_fi' => array('integer', $this->getOriginalGroupTaxId()),
				'mapped_group_tax_fi' => array('integer', $this->getMappedGroupTaxId()),
// fau.
// fau: typeFilter - insert into db
				'type_filter' => array('text', $this->getTypeFilterForDbValue()),
// fau.
// fau: randomSetOrder - update in db
				'order_by' => array('text', $this->getOrderBy()),
// fau.
				'quest_amount' => array('integer', $this->getQuestionAmount()),
				'sequence_pos' => array('integer', $this->getSequencePosition())
		));

		$this->setId($nextId);
	}
	
	// -----------------------------------------------------------------------------------------------------------------
	
	public function getPoolInfoLabel(ilLanguage $lng)
	{
		$poolInfoLabel = sprintf(
			$lng->txt('tst_dynamic_question_set_source_questionpool_summary_string'),
			$this->getPoolTitle(),
			$this->getPoolPath(),
			$this->getPoolQuestionCount()
		);
		
		return $poolInfoLabel;
	}

	// -----------------------------------------------------------------------------------------------------------------
}
