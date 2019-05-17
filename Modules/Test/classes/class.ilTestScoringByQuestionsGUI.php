<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once 'Modules/Test/classes/inc.AssessmentConstants.php';
include_once 'Modules/Test/classes/class.ilTestScoringGUI.php';

/**
 * ilTestScoringByQuestionsGUI
 * @author     Michael Jansen <mjansen@databay.de>
 * @author     Björn Heyser <bheyser@databay.de>
 * @version    $Id$
 * @ingroup    ModulesTest
 * @extends    ilTestServiceGUI
 */
class ilTestScoringByQuestionsGUI extends ilTestScoringGUI
{
	/**
	 * @param ilObjTest $a_object
	 */
	public function __construct(ilObjTest $a_object)
	{
		parent::__construct($a_object);
	}

	/**
	 * @return string
	 */
	protected function getDefaultCommand()
	{
		return 'showManScoringByQuestionParticipantsTable';
	}
	
	/**
	 * @return string
	 */
	protected function getActiveSubTabId()
	{
		return 'man_scoring_by_qst';
	}
	
	/**
	 * @param array $manPointsPost
	 */
	protected function showManScoringByQuestionParticipantsTable($manPointsPost = array())
	{
		global $DIC; /* @var ILIAS\DI\Container $DIC */
		$tpl = $DIC->ui()->mainTemplate();
		
		$DIC->tabs()->activateTab(ilTestTabsManager::TAB_ID_MANUAL_SCORING);
		
		include_once 'Services/jQuery/classes/class.iljQueryUtil.php';
		iljQueryUtil::initjQuery();

		include_once 'Services/YUI/classes/class.ilYuiUtil.php';
		ilYuiUtil::initPanel();
		ilYuiUtil::initOverlay();

		$mathJaxSetting = new ilSetting('MathJax');
		if($mathJaxSetting->get("enable"))
		{
			$tpl->addJavaScript($mathJaxSetting->get("path_to_mathjax"));
		}

		$tpl->addJavaScript("./Services/JavaScript/js/Basic.js");
		$tpl->addJavaScript("./Services/Form/js/Form.js");
		$tpl->addJavascript('./Services/UIComponent/Modal/js/Modal.js');
		$tpl->addCss($this->object->getTestStyleLocation("output"), "screen");

		$this->lng->toJSMap(array('answer' => $this->lng->txt('answer')));

		require_once 'Modules/Test/classes/tables/class.ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI.php';
		$table = new ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI($this);
		
		$table->setManualScoringPointsPostData($manPointsPost);

		$qst_id  = $table->getFilterItemByPostVar('question')->getValue();
		$passNr = $table->getFilterItemByPostVar('pass')->getValue();

		$table_data = array();

		$selected_questionData = null;

		if(is_numeric($qst_id))
		{
			$scoring = ilObjAssessmentFolder::_getManualScoring();
			$info = assQuestion::_getQuestionInfo($qst_id);
			$selected_questionData = $info;
			$type = $info["question_type_fi"];
			if(in_array($type, $scoring))
			{
				$selected_questionData = $info;
			}
		}

		if($selected_questionData && is_numeric($passNr))
		{
			$data = $this->object->getCompleteEvaluationData(FALSE);
			$participants = $data->getParticipants();
			
			require_once 'Modules/Test/classes/class.ilTestParticipantData.php';
			$participantData = new ilTestParticipantData($DIC->database(), $DIC->language());
			$participantData->setActiveIdsFilter(array_keys($data->getParticipants()));
			
			$participantData->setParticipantAccessFilter(
				ilTestParticipantAccessFilter::getScoreParticipantsUserFilter($this->ref_id)
			);
			
			$participantData->load($this->object->getTestId());
			
			foreach($participantData->getActiveIds() as $active_id)
			{
				$participant = $participants[$active_id];
				$testResultData = $this->object->getTestResult($active_id, $passNr - 1);
				foreach($testResultData as $questionData)
				{
					if( !isset($questionData['qid']) || $questionData['qid'] != $selected_questionData['question_id'] )
					{
						continue;
					}

					$table_data[] = array(
						'pass_id'        => $passNr - 1,
						'active_id'      => $active_id,
						'qst_id'         => $questionData['qid'],
						'reached_points' => assQuestion::_getReachedPoints($active_id, $questionData['qid'], $passNr - 1),
						'maximum_points' => assQuestion::_getMaximumPoints($questionData['qid']),
						'participant'    => $participant,
					);
				}
			}
		}
		else
		{
			$table->disable('header');
		}

		if($selected_questionData)
		{
			$maxpoints = assQuestion::_getMaximumPoints($selected_questionData['question_id']);
			$table->setCurQuestionMaxPoints($maxpoints);
			if($maxpoints == 1)
			{
				$maxpoints = ' (' . $maxpoints . ' ' . $this->lng->txt('point') . ')';
			}
			else
			{
				$maxpoints = ' (' . $maxpoints . ' ' . $this->lng->txt('points') . ')';
			}
			$table->setTitle($this->lng->txt('tst_man_scoring_by_qst') . ': ' . $selected_questionData['title'] . $maxpoints . ' ['. $this->lng->txt('question_id_short') . ': ' . $selected_questionData['question_id']  . ']');
		}
		else
		{
			$table->setTitle($this->lng->txt('tst_man_scoring_by_qst'));
		}

		$table->setData($table_data);
		$tpl->setContent($table->getHTML());
	}
	
	protected function saveManScoringByQuestion()
	{
		global $DIC; /* @var ILIAS\DI\Container $DIC */
		
		if(!isset($_POST['scoring']) || !is_array($_POST['scoring']))
		{
			ilUtil::sendFailure($this->lng->txt('tst_save_manscoring_failed_unknown'));
			$this->showManScoringByQuestionParticipantsTable();
			return;
		}
		
		$pass = key($_POST['scoring']);
		$activeData = current($_POST['scoring']);
		
		require_once 'Modules/Test/classes/class.ilTestParticipantData.php';
		$participantData = new ilTestParticipantData($DIC->database(), $DIC->language());
		$participantData->setActiveIdsFilter(array_keys($activeData));
		
		$participantData->setParticipantAccessFilter(
			ilTestParticipantAccessFilter::getScoreParticipantsUserFilter($this->ref_id)
		);
		
		$participantData->load($this->object->getTestId());
		
		include_once 'Modules/TestQuestionPool/classes/class.assQuestion.php';
		include_once 'Modules/Test/classes/class.ilObjTestAccess.php';
		include_once 'Services/Tracking/classes/class.ilLPStatusWrapper.php';

		$oneExceededMaxPoints = false;
		$manPointsPost = array();
		$skipParticipant = array();
		$maxPointsByQuestionId = array();
		foreach($participantData->getActiveIds() as $active_id)
		{
			$questions = $activeData[$active_id];
			
			// check for existing test result data
			if( !$this->object->getTestResult($active_id, $pass) )
			{
				if( !isset($skipParticipant[$pass]) )
				{
					$skipParticipant[$pass] = array();
				}
				
				$skipParticipant[$pass][$active_id] = true;
				
				continue;
			}
			
			foreach((array)$questions as $qst_id => $reached_points)
			{
				if( !isset($manPointsPost[$pass]) )
				{
					$manPointsPost[$pass] = array();
				}

				if( !isset($manPointsPost[$pass][$active_id]) )
				{
					$manPointsPost[$pass][$active_id] = array();
				}

				$maxPointsByQuestionId[$qst_id] = assQuestion::_getMaximumPoints($qst_id);
				
				if( $reached_points > $maxPointsByQuestionId[$qst_id] )
				{
					$oneExceededMaxPoints = true;
				}
				
				$manPointsPost[$pass][$active_id][$qst_id] = $reached_points;
			}
		}
		
		if( $oneExceededMaxPoints )
		{
			ilUtil::sendFailure(sprintf($this->lng->txt('tst_save_manscoring_failed'), $pass + 1));
			$this->showManScoringByQuestionParticipantsTable($manPointsPost);
			return;
		}

// fau: fixManScoringSuccessMessage	- show names of all changed participants
		$changed_ids = array();
		foreach($participantData->getActiveIds() as $active_id)
		{
			$questions = $activeData[$active_id];

			$update_participant = false;
			
			if($skipParticipant[$pass][$active_id])
			{
				continue;
			}

			foreach((array)$questions as $qst_id => $reached_points)
			{
				$update_participant = assQuestion::_setReachedPoints(
					$active_id, $qst_id, $reached_points, $maxPointsByQuestionId[$qst_id], $pass, 1, $this->object->areObligationsEnabled()
				);
			}

				if($update_participant)
				{

					$changed_ids[] = $active_id;
					
					ilLPStatusWrapper::_updateStatus(
						$this->object->getId(), ilObjTestAccess::_getParticipantId($active_id)
					);
				}
			}
		}

		$changed_names = array();
		foreach ($changed_ids as $active_id)
		{
			if ($this->object->getAnonymity() == 0)
			{
				$user_name = $user_name = ilObjUser::_lookupName(ilObjTestAccess::_getParticipantId($active_id));
				$changed_names[] = $user_name['firstname'] . ' ' . $user_name['lastname'];
			}
			else
			{
				$changed_names[] = $lng->txt('anonymous');
			}
		}

		if (!empty($changed_names))
		{
			ilUtil::sendSuccess(sprintf($this->lng->txt('tst_saved_manscoring_successfully'), $pass + 1, implode(', ', $changed_names)), true);

			require_once './Modules/Test/classes/class.ilTestScoring.php';
			$scorer = new ilTestScoring($this->object);
			$scorer->setPreserveManualScores(true);
			$scorer->recalculateSolutions();
		}
// fau.

		$this->showManScoringByQuestionParticipantsTable();
	}

	/**
	 * 
	 */
	protected function applyManScoringByQuestionFilter()
	{
		require_once 'Modules/Test/classes/tables/class.ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI.php';
		$table = new ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI($this);
		$table->resetOffset();
		$table->writeFilterToSession();
		$this->showManScoringByQuestionParticipantsTable();
	}

	/**
	 * 
	 */
	protected function resetManScoringByQuestionFilter()
	{
		require_once 'Modules/Test/classes/tables/class.ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI.php';
		$table = new ilTestManScoringParticipantsBySelectedQuestionAndPassTableGUI($this);
		$table->resetOffset();
		$table->resetFilter();
		$this->showManScoringByQuestionParticipantsTable();
	}

	protected function getAnswerDetail()
	{
		$active_id   = (int)$_GET['active_id'];
		$pass        = (int)$_GET['pass_id'];
		$question_id = (int)$_GET['qst_id'];
		
		if( !$this->getTestAccess()->checkScoreParticipantsAccessForActiveId($active_id) )
		{
			exit; // illegal ajax call
		}
		
		$data = $this->object->getCompleteEvaluationData(FALSE);
		$participant = $data->getParticipant($active_id);
		
		$question_gui = $this->object->createQuestionGUI('', $question_id);

		$tmp_tpl = new ilTemplate('tpl.il_as_tst_correct_solution_output.html', TRUE, TRUE, 'Modules/Test');
		$result_output = $question_gui->getSolutionOutput($active_id, $pass, FALSE, FALSE, FALSE, $this->object->getShowSolutionFeedback(), FALSE, TRUE);
		$tmp_tpl->setVariable('TEXT_YOUR_SOLUTION', $this->lng->txt('answers_of') .' '. $participant->getName());
		$maxpoints = $question_gui->object->getMaximumPoints();

		$add_title = ' ['. $this->lng->txt('question_id_short') . ': ' . $question_id  . ']';
		
		if($maxpoints == 1)
		{
			$tmp_tpl->setVariable('QUESTION_TITLE', $this->object->getQuestionTitle($question_gui->object->getTitle()) . ' (' . $maxpoints . ' ' . $this->lng->txt('point') . ')' . $add_title);
		}
		else
		{
			$tmp_tpl->setVariable('QUESTION_TITLE', $this->object->getQuestionTitle($question_gui->object->getTitle()) . ' (' . $maxpoints . ' ' . $this->lng->txt('points') . ')' . $add_title);
		}
		$tmp_tpl->setVariable('SOLUTION_OUTPUT', $result_output);
		$tmp_tpl->setVariable('RECEIVED_POINTS', sprintf($this->lng->txt('part_received_a_of_b_points'), $question_gui->object->getReachedPoints($active_id, $pass), $maxpoints));

		echo $tmp_tpl->get();
		exit();
	}
}