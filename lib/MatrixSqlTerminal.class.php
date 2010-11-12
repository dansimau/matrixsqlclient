<?php
class MatrixSqlTerminal {

	/**
	 * @var $dsn Database connection string
	 */
	private $dsn = '';
	
	/*
	 * @var $db_type Database type, eg. oci8, pgsql
	 */
	private $db_type = '';

	/**
	 * Constructor - initialises Matrix DAL and attempts to connect to database
	 */
	public function __construct() {

		// Switch the term to (mostly) raw mode
		system("stty raw opost -olcuc -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo");

		// Load database DSN from Matrix's db.inc
		$this->dsn = $GLOBALS['db_conf']['db2'];
		$this->db_type = $GLOBALS['db_conf']['db2']['type'];
		
		// Attempt to connect
		MatrixDAL::dbConnect($this->dsn, $this->db_type);
		MatrixDAL::changeDb($this->db_type);
	}
	
	/**
	 * Destructor function - should restore terminal settings
	 */
	public function __destruct() {
		system("stty sane");
	}
	
	/**
	 * Starts the main interactive terminal
	 */
	public function run() {

		$shell = new SimpleReadline();
		
		$prompt = '=# ';
		$sql = '';
		
		echo "Welcome to matrixsqlclient (alpha), the interative database terminal in PHP." . "\n" . "\n";
		echo "You are now connected." . "\n" . "Database type: " . $this->db_type . "." . "\n";
		
		
		while (1) {
		
			// Prompt for input
			$line = $shell->readline($this->dsn['DSN'] . $prompt);
		
		//	echo "\ndebug: line: " . $line . "\n";
		
			// CTRL-D quits
			if ((substr($line, 0, 4) == 'exit') || (substr($line, 0, 4) == 'quit') || substr($line, strlen($line)-1, strlen($line)) === chr(4)) {
				echo "\n";
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
					trim($sql);
		
					// Add this command to the history
					$shell->readline_add_history($sql);
					
					// Strip semicolon from end if its Oracle
					if ($this->db_type == 'oci') {
						$sql = substr($sql, 0, strlen($sql)-1);
					}
					
					// Run the SQL
					$source_data = MatrixDAL::executeSqlAssoc($sql);
			
					$output = new ArrayToTextTable($source_data);
					$output->showHeaders(true);
			
					echo "\n" . "\n";
					$output->render();
					
					echo "\n" . "(" . count($source_data) . " row";
					if (count($source_data) !== 1) echo "s";
					echo ")" . "\n";
			
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

}
?>
