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
	 * @var $db DbBackend object for the backend/database
	 */
	private $db;

	/**
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
	 * @var $line_buffer line output buffer
	 */
	private $line_buffer = array();

	/**
	 * Constructor - initialises Matrix DAL and attempts to connect to database
	 *
	 * @param $backend name of backend plugin to use to connect
	 */
	public function __construct($backend) {

		$this->resetTerminal(true);

		// Instantiate database backend plugin
		$this->db = new DbBackend($backend);

		// Instantiate/initialise stuff
		$this->shell = new SimpleReadline();

		// History storage
		if (!isset($_ENV['HOME'])) {
			$history_storage_file = '/tmp';
		} else {
			$history_storage_file = $_ENV['HOME'];
		}
		$this->history_storage = new HistoryStorage($history_storage_file . '/.matrixsqlclient_history', true);
		$this->shell->history = $this->history_storage->getData();
	}
	
	/**
	 * Destructor function - should restore terminal settings
	 */
	public function __destruct() {
		$this->restoreTerminal();
	}

	/**
	 * Connects the db backend
	 *
	 * @param $dsn connection string for database
	 */
	public function connect($dsn) {
		$this->db->connect($dsn);
	}

	/**
	 * Starts the main interactive terminal
	 */
	public function run() {

		$prompt = '=# ';
		$sql = '';

		ob_start();
		echo "Welcome to matrixsqlclient (alpha";
		if (!empty($GLOBALS['rev'])) echo ", rev " . $GLOBALS['rev'];
		echo "), the interative database terminal in PHP.";
		echo "\n\nYou are now connected.";
		echo "\nDatabase type: " . $this->db->getDbType() . $this->db->getDbVersion() . ".\n";
		ob_end_flush();
		
		while (1) {
		
			// Prompt for input
			$line = $this->shell->readline($this->db->getDbName() . $prompt);
		
			// Exits
			if ((substr(trim($line), 0, 4) == 'exit') || (substr(trim($line), 0, 4) == 'quit') || (substr(trim($line), 0, 2) == '\q')) {
				echo "\n";
				exit;
			}
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

				echo "\n";

				$sql = trim($sql);

				// Add this command to the history
				$this->shell->readline_add_history($sql);

				try {
					// Run the SQL
					$source_data = $this->db->execute($sql);
				}
				catch (Exception $e) {
					echo "\n" . $e->getMessage() . "\n";
					continue;
				}

				// Find out what type of query this is and what to do with it
				if (strtoupper(substr($sql, 0, 6)) == "UPDATE") {
				    echo "UPDATE " . count($source_data);
				}
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
				// SELECTs and default
				else {

					// Only render the table if rows were returned
					if (!empty($source_data)) {

						// Render the table
						$table = new ArrayToTextTable($source_data);
						$table->showHeaders(true);

						$this->addToLinesBuffer(explode("\n", $table->render(true)));
					}

					// Build count summary (at end of table) and add to line buffer
					$count_str = "\n(" . count($source_data) . " row";
					if (count($source_data) !== 1) $count_str .= "s";
					$count_str .= ")";

					$this->addToLinesBuffer(array($count_str));

					// Output the data
					$this->printLines();
				}
		
				// Reset the prompt cause its a new query
				$prompt = '=# ';
				$sql = '';
		
			} elseif (strlen(trim($sql)) > 0) {
				// We're in the middle of some SQL, so modify the prompt slightly to show that
				// (like psql does)
				$prompt = '-# ';
			}

			// Update persistent history store
			$this->history_storage->setData($this->shell->history);
		}
	}
	
	/**
	 * Gets the terminal ready for our own use (switch it to raw mode).
	 *
	 * @param bool whether or not to save the existing terminal settings for restoring later
	 */
	public function resetTerminal($save_existing=FALSE) {

		// Save existing settings
		if ($save_existing) {
			$this->tty_saved = `stty -g`;
		}

		// Reset terminal
		system("stty raw opost -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo isig intr undef");
	}

	/**
	 * Restores the terminal to the previously saved state.
	 */
	public function restoreTerminal() {
		system("stty '" . trim($this->tty_saved) . "'");
	}

	/**
	 * Returns the height and width of the terminal.
	 *
	 * @return array An array with two elements - number of rows and number of columns.
	 */
	public function getTtySize() {
		return explode("\n", `printf "lines\ncols" | tput -S`);
	}

	/**
	 * Prints the specified number of lines from the line buffer.
	 *
	 * @param $n number of lines to print, or 0 to print all lines, with pagination (default)
	 */
	public function printLines($n=0) {

		$lines_printed = array();

		if ($n > 0) {

			// Print a specific number of lines
			$line_buffer_len = count($this->line_buffer);
			for ($i=0; $i<$line_buffer_len && $i<$n; $i++) {
				$line = array_shift($this->line_buffer);
				echo $line;
				$lines_printed[] = $line;
			}

			// Return the lines printed
			return $lines_printed;

		} else {

			// Get current terminal size
			$tty_size = $this->getTtySize();

			if (count($this->line_buffer) < $tty_size[0]) {

				// Print all lines, if it fits on the tty
				$this->printLines(count($this->line_buffer));

			} else {

				// Otherwise, let's paginate...

				// Print first chunk
				$last_lines = $this->printLines($tty_size[0]-1);
				if ($last_lines[count($last_lines)-1][strlen($last_lines[count($last_lines)-1])-1] != "\n") echo "\n";
				echo "\033[30;47m" . "--More--" . "\033[0m";

				// Print rest of the chunks
				while (1) {

					// Stop printing chunks if the line buffer is empty
					if (!count($this->line_buffer) > 0) {
						// Backspace the "--More--"
						TerminalDisplay::backspace(8);
						break;
    				}

					// Read user input
					$c = SimpleReadline::readKey();

					switch ($c) {

						// User wants more lines, one at a time
						case chr(10):

							// Backspace the "--More--"
							TerminalDisplay::backspace(8);

							$last_lines = $this->printLines(1);
							if ($last_lines[count($last_lines)-1][strlen($last_lines[count($last_lines)-1])-1] != "\n") echo "\n";
							echo "\033[30;47m" . "--More--" . "\033[0m";

							break;

						// User wants to end output (ie. 'q', CTRL+C)
						case chr(3):
						case chr(113):

							// Backspace the "--More--"
							TerminalDisplay::backspace(8);

							// Clear line buffer
							$this->clearLineBuffer();

							return;
							break;

						default:
							SimpleReadline::bell();
							continue;
					}
				}
			}
		}
	}

	/**
	 * Adds data to the line buffer.
	 *
	 * @param $data array of lines to add to the buffer
	 */
	public function addToLinesBuffer($data) {

		// Get current terminal size
		$tty_size = $this->getTtySize();

		// Loop through data so we can split lines at terminal size
		for ($i=0; $i<count($data); $i++) {

			// Add newlines to the end of each proper line
			$data[$i] .= "\n";

			// Split line at terminal width and add to output
			foreach (str_split($data[$i], (int)$tty_size[1]) as $line) {
				$this->line_buffer[] = $line;
			}
		}
	}

	/**
	 * Erases everything in the line buffer.
	 */
	public function clearLineBuffer() {
		$this->line_buffer = array();
	}
}
?>
