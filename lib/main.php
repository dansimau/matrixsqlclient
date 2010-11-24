<?php
// Install signal handler (if possible)
if (function_exists("pcntl_signal")) {
	declare(ticks = 1);

	function sig_handler($signal) {
		global $matrixSqlTerminal;
		switch ($signal) {
			// Reset the terminal again when the process is unfrozen
			case SIGCONT:
				$matrixSqlTerminal->resetTerminal();
				break;
		}
	}
	pcntl_signal(SIGCONT, "sig_handler");
}

error_reporting(E_ALL);

// Run the terminal
$matrixSqlTerminal = new MatrixSqlTerminal('MatrixDAL');
$matrixSqlTerminal->connect($_SERVER['argv'][1]);
$matrixSqlTerminal->run();
?>
