<?php
// TODO: These constants are for SimpleReadline - need to move them into there
define('UP', chr(27).chr(91).chr(65));
define('DOWN', chr(27).chr(91).chr(66));
define('RIGHT', chr(27).chr(91).chr(67));
define('LEFT', chr(27).chr(91).chr(68));

// Get command line params
$SYSTEM_ROOT = (isset($_SERVER['argv'][1])) ? $_SERVER['argv'][1] : '';
if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
}

require_once $SYSTEM_ROOT.'/fudge/dev/dev.inc';
require_once $SYSTEM_ROOT.'/core/include/general.inc';
require_once $SYSTEM_ROOT.'/core/lib/DAL/DAL.inc';
require_once $SYSTEM_ROOT.'/core/lib/MatrixDAL/MatrixDAL.inc';
require_once $SYSTEM_ROOT.'/data/private/conf/db.inc';

error_reporting(E_ALL);

// Run the terminal
$matrixSqlTerminal = new MatrixSqlTerminal();
$matrixSqlTerminal->run();
?>
