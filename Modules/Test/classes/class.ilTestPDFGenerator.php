<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilTestPDFGenerator
 * 
 * Class that handles PDF generation for test and assessment.
 * 
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 * 
 */
class ilTestPDFGenerator 
{
	const PDF_OUTPUT_DOWNLOAD = 'D';
	const PDF_OUTPUT_INLINE = 'I';
	const PDF_OUTPUT_FILE = 'F';

	/**
	 * @param $html
	 * @return string
	 */
	private static function removeScriptElements($html)
	{
		if(!is_string($html) || !strlen(trim($html)))
		{
			return $html;
		}

		$dom = new DOMDocument("1.0", "utf-8");
		if(!@$dom->loadHTML('<?xml encoding="UTF-8">' . $html))
		{
			return $html;
		}

		$invalid_elements = array();

		$script_elements     = $dom->getElementsByTagName('script');
		foreach($script_elements as $elm)
		{
			$invalid_elements[] = $elm;
		}

		foreach($invalid_elements as $elm)
		{
			$elm->parentNode->removeChild($elm);
		}

		$dom->encoding = 'UTF-8';
		$cleaned_html = $dom->saveHTML();
		if(!$cleaned_html)
		{
			return $html;
		}

		return $cleaned_html;
	}

	public static function generatePDF($pdf_output, $output_mode, $filename=null)
	{
		$pdf_output = self::preprocessHTML($pdf_output);
		
		if (substr($filename, strlen($filename) - 4, 4) != '.pdf')
		{
			$filename .= '.pdf';
		}
		
		require_once './Services/PDFGeneration/classes/class.ilPDFGeneration.php';
		
		$job = new ilPDFGenerationJob();
		$job->setAutoPageBreak(true)
			->setCreator('ILIAS Test')
			->setFilename($filename)
			->setMarginLeft('20')
			->setMarginRight('20')
			->setMarginTop('20')
			->setMarginBottom('20')
			->setOutputMode($output_mode)
			->addPage($pdf_output);
		
		ilPDFGeneration::doJob($job);
	}
	
	public static function preprocessHTML($html)
	{
		// fim: [tex] process latex for pdf generation
		$mathJaxSetting = new ilSetting("MathJax");
		if ($mathJaxSetting->get("enable"))
		{
			// process latex images again
			// disable mathjax for this call (without changing the database setting)
			// this forces the server-side generation of images
			// add "processed" flag to indicate that delimiters are already converted
			$mathJaxSetting->set('enable','0', false);
			$html = ilUtil::insertLatexImages($html, '', '', true);
		}
		// fim.

		// fim: [pdf] add styles for phantomjs
		global $ilCust;
		if ($ilCust->getSetting('pdf_engine') == 'phantomjs')
		{
			return
				'<html><head>'
				.'<meta http-equiv="content-type" content="text/html; charset=UTF-8" />'
				.'<style>'

				. file_get_contents(ilUtil::getStyleSheetLocation('filesystem'))		// Delos
				. file_get_contents(self::getTemplatePath('ta.css'))					// Test
				. file_get_contents(self::getTemplatePath('test_phantomjs.css'))		// PDF

				. '</style></head><body>'.$html
				.'</body></html>';
		}
		else
		{
			$html = self::removeScriptElements($html);
			$pdf_css_path = self::getTemplatePath('test_pdf.css');
			return '<style>' . file_get_contents($pdf_css_path)	. '</style>' . $html;
		}
		// fim.
	}

	protected static function getTemplatePath($a_filename)
	{
			$module_path = "Modules/Test/";

			// use ilStyleDefinition instead of account to get the current skin
			include_once "Services/Style/classes/class.ilStyleDefinition.php";
			if (ilStyleDefinition::getCurrentSkin() != "default")
			{
				$fname = "./Customizing/global/skin/".
					ilStyleDefinition::getCurrentSkin()."/".$module_path.basename($a_filename);
			}

			if($fname == "" || !file_exists($fname))
			{
				$fname = "./".$module_path."templates/default/".basename($a_filename);
			}
		return $fname;
	}

}