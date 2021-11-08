<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * ilFrameTargetInfo
 * @author	 Alex Killing <alex.killing@gmx.de>
 * @version	$Id$
 */
class ilFrameTargetInfo
{
    /**
     * Get content frame name
     * @static
     * @param string $a_class
     * @param string $a_type
     * @return string
     */
    public static function _getFrame($a_class, $a_type = '')
    {
        // LTI
        global $DIC;
        $ltiview = $DIC['lti'];

        switch ($a_type) {
            default:
                switch ($a_class) {
                    case 'RepositoryContent':
                    case 'MainContent':
                        // LTI
                        if ($ltiview->isActive()) {
                            return '_self';
                        }
                        return '_top';

                    case 'ExternalContent':
                        return '_blank';
                }
        }

        return '';
    }
}
