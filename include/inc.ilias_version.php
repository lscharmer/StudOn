<?php
/* Copyright (c) 1998-2014 ILIAS open source e-Learning e.V., Extended GPL, see docs/LICENSE */

/**
* sets ilias version (this file shouldn't be merged between cvs branches)
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
* @package ilias-core
*/
define("ILIAS_VERSION", "5.1.26 2018-04-25");
define("ILIAS_VERSION_NUMERIC", "5.1.26");			// must be always x.y.z: x, y and z are numbers

// fau: versionSuffix - define a version with suffix for including css and js files
// please increase a suffix number if a css or js file is locally changed!
define("ILIAS_VERSION_SUFFIX", ILIAS_VERSION_NUMERIC . ".14");
// fau.
?>
