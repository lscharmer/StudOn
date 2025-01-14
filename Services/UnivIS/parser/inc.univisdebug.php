<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2007-2008 Starbugs (univis2typo3@googlegroups.com)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * fau: univisImport - debug functions for univis classes.
 *
 * Taken from the univis2typo3 project and adapted for ILIAS
 *
 * Related global constants (client.ini.php settings):
 * DEBUG    show parsing steps
 * DEVMODE  sohw parser errors
 *
 * @author	Starbugs <univis2typo3@googlegroups.com>
 * @modifier Fred neumann <fred.neumann@fim.uni-erlangen.de>
 */


function U2T3_DEBUG_PARSER($depth, $message)
{
    if (DEBUG == true) {
        global $depth;
        for ($i = 0; $i < $depth; $i++) {
            echo "&nbsp;&nbsp;";
        }
        echo $message . '<br />';
    }
}

function U2T3_DEBUG($message)
{
    if (DEBUG == true) {
        echo $message . '<br />';
    }
}

function U2T3_ERROR($error_message)
{
    if (DEVMODE == true) {
        echo "Error: " . $error_message;
    }
}
