<?php
/*
 * LibreNMS
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */
require 'includes/graphs/common.inc.php';
$rrdfilename = $config['rrd_dir'].'/'.$device['hostname'].'/canopy-generic-freq.rrd';
if (file_exists($rrdfilename)) {
    $rrd_options .= " COMMENT:'Ghz           Now     \\n'";
    $rrd_options .= ' DEF:freq='.$rrdfilename.':freq:AVERAGE ';
    $rrd_options .= " LINE2:freq#FF0000:'Frequency       ' ";
    $rrd_options .= ' GPRINT:freq:LAST:%0.2lf%s\\\l ';
}