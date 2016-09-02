<?php

/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Online help application class
 *
 * @author	Alex Killing <alex.killing@gmx.de>
 * @version	$Id$
 */
class ilHelp
{
	/**
	 * Get tooltip for id
	 *
	 * @param
	 * @return
	 */
	static function getTooltipPresentationText($a_tt_id)
	{
		global $ilDB, $ilSetting, $ilUser;
		
		
		if ($ilUser->getLanguage() != "de")
		{
			return "";
		}
		
		if ($ilSetting->get("help_mode") == "1")
		{
			return "";
		}
		
		if (OH_REF_ID > 0)
		{
			$module_id = 0;
		}
		else
		{
			$module_id = (int) $ilSetting->get("help_module");
			if ($module_id == 0)
			{
				return "";
			}
		}
		
// fau: sqlCache - use sql cache
		$set = $ilDB->query("SELECT SQL_CACHE tt_text FROM help_tooltip ".
			" WHERE tt_id = ".$ilDB->quote($a_tt_id, "text").
			" AND module_id = ".$ilDB->quote($module_id, "integer")
			);
// fau.
		$rec = $ilDB->fetchAssoc($set);
		if ($rec["tt_text"] != "")
		{
			$t = $rec["tt_text"];
			// fim: [help] make showing of ids independent from OH_REF_ID
			global $ilCust;
			if ($ilCust->getSetting("help_show_ids"))
			{
				$t.="<br/><i class='small'>".$a_tt_id."</i>";
			}
			// fim.
			return $t;
		}
		else // try to get general version
		{
			$fu = strpos($a_tt_id, "_");
			$gen_tt_id = "*".substr($a_tt_id, $fu);
// fau: sqlCache - use sql cache
			$set = $ilDB->query("SELECT SQL_CACHE tt_text FROM help_tooltip ".
				" WHERE tt_id = ".$ilDB->quote($gen_tt_id, "text").
				" AND module_id = ".$ilDB->quote($module_id, "integer")
				);
// fau.
			$rec = $ilDB->fetchAssoc($set);
			if ($rec["tt_text"] != "")
			{
				$t = $rec["tt_text"];
				// fim: [help] make showing of ids independent from OH_REF_ID
				global $ilCust;
				if ($ilCust->getSetting("help_show_ids"))
				{
					$t.="<br/><i class='small'>".$a_tt_id."</i>";
				}
				// fim.
				return $t;
			}
		}
		// fim: [help] make showing of ids independent from OH_REF_ID
		global $ilCust;
		if ($ilCust->getSetting("help_show_ids"))
		{
			return "<i>".$a_tt_id."</i>";
		}
		// fim.
		return "";
	}

	/**
	 * Get object_creation tooltip tab text
	 *
	 * @param string $a_tab_id tab id
	 * @return string tooltip text
	 */
	static function getObjCreationTooltipText($a_type)
	{
		return self::getTooltipPresentationText($a_type."_create");
	}

	/**
	 * Get main menu tooltip
	 *
	 * @param string $a_mm_id 
	 * @return string tooltip text
	 */
	static function getMainMenuTooltip($a_item_id)
	{
		return self::getTooltipPresentationText($a_item_id);
	}

	
	/**
	 * Get all tooltips
	 *
	 * @param
	 * @return
	 */
	static function getAllTooltips($a_comp = "", $a_module_id = 0)
	{
		global $ilDB;
// fau: sqlCache - use sql cache
		$q = "SELECT SQL_CACHE * FROM help_tooltip";
		$q.= " WHERE module_id = ".$ilDB->quote($a_module_id, "integer");
		if ($a_comp != "")
		{
			$q.= " AND comp = ".$ilDB->quote($a_comp, "text");
		}
// fau.
		$set = $ilDB->query($q);
		$tts = array();
		while ($rec  = $ilDB->fetchAssoc($set))
		{
			$tts[$rec["id"]] = array("id" => $rec["id"], "text" => $rec["tt_text"],
				"tt_id" => $rec["tt_id"]);
		}
		return $tts;
	}
	
	/**
	 * Add tooltip
	 *
	 * @param
	 * @return
	 */
	static function addTooltip($a_tt_id, $a_text, $a_module_id = 0)
	{
		global $ilDB;
		
		$fu = strpos($a_tt_id, "_");
		$comp = substr($a_tt_id, 0, $fu);
		
		$nid = $ilDB->nextId("help_tooltip");
		$ilDB->manipulate("INSERT INTO help_tooltip ".
			"(id, tt_text, tt_id, comp,module_id) VALUES (".
			$ilDB->quote($nid, "integer").",".
			$ilDB->quote($a_text, "text").",".
			$ilDB->quote($a_tt_id, "text").",".
			$ilDB->quote($comp, "text").",".
			$ilDB->quote($a_module_id, "integer").
			")");
	}
	
	/**
	 * Update tooltip
	 *
	 * @param
	 * @return
	 */
	static function updateTooltip($a_id, $a_text, $a_tt_id)
	{
		global $ilDB;

		$fu = strpos($a_tt_id, "_");
		$comp = substr($a_tt_id, 0, $fu);
		
		$ilDB->manipulate("UPDATE help_tooltip SET ".
			" tt_text = ".$ilDB->quote($a_text, "text").", ".
			" tt_id = ".$ilDB->quote($a_tt_id, "text").", ".
			" comp = ".$ilDB->quote($comp, "text").
			" WHERE id = ".$ilDB->quote($a_id, "integer")
			);
	}
	
	
	/**
	 * Get all tooltip components
	 *
	 * @param
	 * @return
	 */
	static function getTooltipComponents($a_module_id = 0)
	{
		global $ilDB, $lng;
		
// fau: sqlCache - use sql cache
		$set = $ilDB->query("SELECT DISTINCT SQL_CACHE comp FROM help_tooltip ".
			" WHERE module_id = ".$ilDB->quote($a_module_id, "integer").
			" ORDER BY comp ");
// fau.
		$comps[""] = "- ".$lng->txt("help_all")." -";
		while ($rec = $ilDB->fetchAssoc($set))
		{
			$comps[$rec["comp"]] = $rec["comp"];
		}
		return $comps;
	}
	
	/**
	 * Delete tooltip
	 *
	 * @param
	 * @return
	 */
	static function deleteTooltip($a_id)
	{
		global $ilDB;
		
		$ilDB->manipulate("DELETE FROM help_tooltip WHERE ".
			" id = ".$ilDB->quote($a_id, "integer")
			);
	}
	
	/**
	 * Delete tooltips of module
	 *
	 * @param
	 * @return
	 */
	static function deleteTooltipsOfModule($a_id)
	{
		global $ilDB;
		
		$ilDB->manipulate("DELETE FROM help_tooltip WHERE ".
			" module_id = ".$ilDB->quote($a_id, "integer")
			);
		
	}

	/**
	 * Get help lm id
	 *
	 * @return int help learning module id
	 */
	static function getHelpLMId()
	{
		global $ilSetting;

		$lm_id = 0;

		if (OH_REF_ID > 0)
		{
			$lm_id = ilObject::_lookupObjId(OH_REF_ID);
		}
		else
		{
			$hm = (int) $ilSetting->get("help_module");
			if ($hm > 0)
			{
				include_once("./Services/Help/classes/class.ilObjHelpSettings.php");
				$lm_id = ilObjHelpSettings::lookupModuleLmId($hm);
			}
		}

		return $lm_id;
	}
	
}
?>