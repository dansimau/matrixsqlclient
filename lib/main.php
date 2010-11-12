<?php
// Get command line params
$SYSTEM_ROOT = (isset($_SERVER['argv'][1])) ? $_SERVER['argv'][1] : '';
if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
}

require_once(dirname(__FILE__) . '/lib/MatrixSqlTerminal.class.php');

error_reporting(E_ALL);

// Run the terminal
$matrixSqlTerminal = new MatrixSqlTerminal();
$matrixSqlTerminal->run();
?>

