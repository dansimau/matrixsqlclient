<?php
// Install signal handler (if possible)
if (function_exists("pcntl_signal")) {
	declare(ticks = 1);

	function sig_handler($signal)
	{
		global $matrixSqlTerminal;
		switch ($signal) {
			case SIGINT:
				// Tell SQL client to cancel what it's doing
				$matrixSqlTerminal->cancel();
				break;
			case SIGCONT:
				// Reset the terminal again when the process is unfrozen
				$matrixSqlTerminal->resetTerminal();
				break;
		}
	}
	pcntl_signal(SIGCONT, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
}

error_reporting(E_ALL);
mb_internal_encoding("UTF-8");

// Run the terminal
$matrixSqlTerminal = new InteractiveSqlTerminal('MatrixDAL');
$matrixSqlTerminal->connect($_SERVER['argv'][1]);
$matrixSqlTerminal->setOption("HISTFILE", "~/.matrixsqlclient_history");
$matrixSqlTerminal->run();
?>
