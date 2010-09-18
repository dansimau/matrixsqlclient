<?php
require_once('lib/MatrixSqlTerminal.class.php');

error_reporting(E_ALL);

// Get command line params
$SYSTEM_ROOT = (isset($_SERVER['argv'][1])) ? $_SERVER['argv'][1] : '';
if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
}

// Install signal handlers
declare(ticks = 1);
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

// Run the terminal
$matrixSqlTerminal = new MatrixSqlTerminal();
$matrixSqlTerminal->run();
?>

