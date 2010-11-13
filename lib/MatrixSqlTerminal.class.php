<?php
/**
 * Matrix SQL client - the main class.
 *
 * Classes this relies on:
 *  - SimpleReadline
 *  - HistoryStorage
 *
 * @author Daniel Simmons <dan@dans.im>
 * @copyright Copyright (C) 2010 Daniel Simmons
 */
class MatrixSqlTerminal {

	/**
	 * @var $dsn Database connection string
	 */
	private $dsn = '';
	
	/*
	 * @var $db_type Database type, eg. oci8, pgsql
	 */
	private $db_type = '';

	/*
	 * @var $tty_saved Stores stty string of saved terminal settings
	 */
	private $tty_saved = '';

	/**
	 * @var $history_stage HistoryStorage class that saves command history to a file
	 */
	private $history_storage;
	
	/**
	 * @var $shell SimpleReadline object
	 */
	private $shell;

	/**
	 * Constructor - initialises Matrix DAL and attempts to connect to database
	 */
	public function __construct() {

		$this->reset_terminal(true);

		// Load database DSN from Matrix's db.inc
		$this->dsn = $GLOBALS['db_conf']['db2'];
		$this->db_type = $GLOBALS['db_conf']['db2']['type'];
		
		// Attempt to connect
		MatrixDAL::dbConnect($this->dsn, $this->db_type);
		MatrixDAL::changeDb($this->db_type);
		
		// Instantiate/initialise stuff
		$this->shell = new SimpleReadline();
		$this->history_storage = new HistoryStorage($_ENV['HOME'] . '/.matrixsqlclient_history', true);
		$this->shell->history = $this->history_storage->get_data();
	}
	
	/**
	 * Destructor function - should restore terminal settings
	 */
	public function __destruct() {
		$this->restore_terminal();
	}
	
	/**
	 * Starts the main interactive terminal
	 */
	public function run() {

		$prompt = '=# ';
		$sql = '';
		
		echo "Welcome to matrixsqlclient (alpha), the interative database terminal in PHP." . "\n" . "\n";
		echo "You are now connected." . "\n" . "Database type: " . $this->db_type . "." . "\n";
		
		
		while (1) {
		
			// Prompt for input
			$line = $this->shell->readline($this->dsn['DSN'] . $prompt);
		
		//	echo "\ndebug: line: " . $line . "\n";
		
			// Exits
			if ((substr($line, 0, 4) == 'exit') || (substr($line, 0, 4) == 'quit')) {
				echo "\n";
				exit;
			}
			
			// CTRL-D
			if (substr($line, strlen($line)-1, strlen($line)) === chr(4)) {
				echo "\q\n";
				exit;
			}
			
			// CTRL-C cancels any current query
			if (ord(substr($line, strlen($line)-1, strlen($line))) === 3) {
				$sql = '';
				$line = '';
				$prompt = '=# ';
				continue;
			}
		
			$sql .= "\n" . $line;
		
			// If the current sql string buffer has a semicolon in it, we're ready to run
			// the SQL!
			if (strpos($sql, ';')) {
				try {
					$sql = trim($sql);
		
					// Add this command to the history
					$this->shell->readline_add_history($sql);
					$this->history_storage->set_data($this->shell->history);
					
					// Strip semicolon from end if its Oracle
					if ($this->db_type == 'oci') {
						$sql = substr($sql, 0, strlen($sql)-1);
					}
					
					// Run the SQL
					$source_data = MatrixDAL::executeSqlAssoc($sql);

					echo "\n";

					// UPDATE
					if (strtoupper(substr($sql, 0, 6)) == "UPDATE") {
						echo "UPDATE " . count($source_data);
					}

					// Transaction stuff
					elseif ((strtoupper(substr($sql, 0, 5)) == "BEGIN") ||
					        (strtoupper(substr($sql, 0, 5)) == "START TRANSACTION")) {
						echo "BEGIN";
					}
					elseif ((strtoupper(substr($sql, 0, 5)) == "ABORT") ||
					        (strtoupper(substr($sql, 0, 5)) == "ROLLBACK")) {
						echo "ROLLBACK";
					}
					elseif (strtoupper(substr($sql, 0, 6)) == "COMMIT") {
						echo "COMMIT";
					}

					// SELECT
					else {

						// Only render the table if rows were returned
						if (!empty($source_data)) {
	
							$output = new ArrayToTextTable($source_data);
							$output->showHeaders(true);
							$output->render();
						}
						
						echo "\n" . "(" . count($source_data) . " row";
						if (count($source_data) !== 1) echo "s";
						echo ")" . "\n";
					}
			
					unset($output);
				}
				catch (Exception $e) {
					echo "\n" . $e->getMessage() . "\n";
				}
		
				// Reset the prompt cause its a new query
				$prompt = '=# ';
				$sql = '';
		
			} elseif (strlen(trim($sql)) > 0) {
				// We're in the middle of some SQL, so modify the prompt slightly to show that
				// (like psql does)
				$prompt = '-# ';
			}
		}
	}
	
	/**
	 * Gets the terminal ready for our own use (switch it to raw mode).
	 *
	 * @param bool whether or not to save the existing terminal settings for restoring later
	 */
	public function reset_terminal($save_existing=FALSE) {

		// Save existing settings
		if ($save_existing) {
			$this->tty_saved = `stty -g`;
		}

		// Reset terminal
		system("stty raw opost -olcuc -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo isig intr undef");
	}

	/**
	 * Restores the terminal to the previously saved state.
	 */
	public function restore_terminal() {
		system("stty '" . trim($this->tty_saved) . "'");
	}
}
?>
