<?php
/**
 * SQL client - the main class.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @copyright 2010 Daniel Simmons
 */
class InteractiveSqlTerminal
{
	/**
	 * @var $db DbBackend object for the backend/database
	 */
	private $_db;

	/**
	 * @var $tty_saved Stores stty string of saved terminal settings
	 */
	private $_tty_saved = '';

	/**
	 * @var $history_stage HistoryStorage class that saves command history to a file
	 */
	private $_history_storage;
	
	/**
	 * @var $shell SimpleReadline object
	 */
	private $_shell;

	/**
	 * @var $line_buffer line output buffer
	 */
	private $_line_buffer = array();

	/**
	 * @var $options An array with a list of options and values
	 */
	private $_options = array(
		'HISTSIZE' => 500,
		'timing' => "off",
		'disable-completion' => "off"
	);

	/**
	 * Constructor - initialises Matrix DAL and attempts to connect to database
	 *
	 * @param string $backend name of backend plugin to use to connect
	 */
	public function __construct($backend)
	{

		$this->_resetTerminal(true);

		// Instantiate database backend plugin
		$this->_db = new DbBackend($backend);

		// Instantiate/initialise stuff
		$this->_shell = new SimpleReadline();

		// History storage
		if (!isset($_ENV['HOME'])) {
			$history_storage_file = '/tmp';
		} else {
			$history_storage_file = $_ENV['HOME'];
		}
		$this->_history_storage = new HistoryStorage($history_storage_file . '/.matrixsqlclient_history', true);

		foreach ($this->_history_storage->getData() as $item) {
			$this->_shell->addHistoryItem($item);
		}

		// Parse options; set autocomplete on/off, etc.
		$this->_parseOptions();
	}
	
	/**
	 * Destructor function - should restore terminal settings
	 */
	public function __destruct()
	{
		$this->restoreTerminal();
	}

	/**
	 * Connects the db backend
	 *
	 * @param string $dsn connection string for database
	 *
	 * @return void
	 */
	public function connect($dsn)
	{
		$this->_db->connect($dsn);
	}

	/**
	 * Starts the main interactive terminal
	 *
	 * @return void
	 */
	public function run()
	{
		$prompt = '=# ';
		$sql = '';

		ob_start();
		echo "Welcome to matrixsqlclient (alpha";
		if (!empty($GLOBALS['rev'])) {
			echo ", rev " . $GLOBALS['rev'];
		}
		echo "), the interactive database terminal in PHP.";
		echo "\n\nYou are now connected.";
		echo "\nDatabase type: " . $this->_db->getDbType() . $this->_db->getDbVersion() . ".\n";
		ob_end_flush();
		
		while (1) {
		
			// Prompt for input
			$line = $this->_shell->readline($this->_db->getDbName() . $prompt);

			// Exits
			if ((mb_substr(trim($line), 0, 4) == 'exit') || (mb_substr(trim($line), 0, 4) == 'quit') || (mb_substr(trim($line), 0, 2) == '\q')) {
				echo "\n";
				exit;
			}
			if (mb_substr($line, mb_strlen($line)-1, mb_strlen($line)) === chr(4)) {
				echo "\q\n";
				exit;
			}

			// CTRL-C cancels any current query
			if (ord(mb_substr($line, mb_strlen($line)-1, mb_strlen($line))) === 3) {
				$sql = '';
				$line = '';
				$prompt = '=# ';
				continue;
			}

			if (mb_strlen($line) > 0) {
				// Add this command to the history
				$this->_shell->readline_add_history(strtr($line, "\n", " "));
			}

			if (mb_substr(trim($line), 0, 7) == "\\timing") {

				$this->_setOption("timing", !$this->_getOptionValue("timing"));

				if ($this->_getOptionValue("timing")) {
					echo "\nTiming is on.";
				} else {
					echo "\nTiming is off.";
				}

				continue;
			}

			// "\set" command
			if (strlen(trim($sql)) === 0 && mb_substr(trim($line), 0, 4) == "\set") {

				$params = explode(" ", $line, 3);

				// "\set" with no options - show existing options/values
				if (count($params) === 1) {

					$options = $this->_getOptions();

					if (count($options) > 0) {

						foreach ($this->_getOptions() as $option => $value) {
							$value = ($value === true) ? "on" : $value;
							$value = ($value === false) ? "off" : $value;
							echo "\n" . $option . " = '" . $value . "'";
						}
					}

				// "set" a particular value
				} else {

					$params = array_pad($params, 3, "");
					$this->_setOption($params[1], $params[2]);
					$this->_parseOptions();
				}

				continue;
			}

			$sql .= "\n" . $line;

			// If the SQL string is terminated with a semicolon, or the DB module wants
			// to accept it (eg. for a macro), then execute it
			if ($this->_db->matchesMacro($sql) || mb_strpos($sql, ';')) {

				echo "\n";

				$sql = trim($sql);

				try {
					// Run the SQL
					$source_data = $this->_db->execute($sql);
				}
				catch (Exception $e) {
					echo "\n" . $e->getMessage() . "\n";

					// Reset the prompt cause its a new query
					$prompt = '=# ';
					$sql = '';

					continue;
				}

				// Find out what type of query this is and what to do with it
				if (mb_strtoupper(mb_substr($sql, 0, 6)) == "UPDATE") {
					echo "UPDATE " . count($source_data);
				} elseif ((mb_strtoupper(mb_substr($sql, 0, 5)) == "BEGIN") ||
						(mb_strtoupper(mb_substr($sql, 0, 5)) == "START TRANSACTION")) {
					echo "BEGIN";
				} elseif ((mb_strtoupper(mb_substr($sql, 0, 5)) == "ABORT") ||
						(mb_strtoupper(mb_substr($sql, 0, 5)) == "ROLLBACK")) {
					echo "ROLLBACK";
				} elseif (mb_strtoupper(mb_substr($sql, 0, 6)) == "COMMIT") {
					echo "COMMIT";
				} else {

					$this->_addToLinesBuffer(array(''));

					// Only render the table if rows were returned
					if (!empty($source_data)) {

						// Render the table
						$table = new ArrayToTextTable($source_data);
						$table->showHeaders(true);

						$this->_addToLinesBuffer(explode("\n", $table->render(true)));
					}

					// Build count summary (at end of table) and add to line buffer
					$count_str = "(" . count($source_data) . " row";
					if (count($source_data) !== 1) {
						$count_str .= "s";
					}
					$count_str .= ")";

					$this->_addToLinesBuffer(array($count_str));

					if ($this->_getOptionValue("timing")) {
						// Output amount of time this query took
						$this->_addToLinesBuffer(array("", "Time: " . $this->_db->getQueryExecutionTime() . " ms"));
					}

					// Output the data
					$this->_printLines();
				}
		
				// Reset the prompt cause its a new query
				$prompt = '=# ';
				$sql = '';
		
			} elseif (mb_strlen(trim($sql)) > 0) {
				// We're in the middle of some SQL, so modify the prompt slightly to show that
				// (like psql does)
				$prompt = '-# ';
			}

			// Update persistent history store
			$this->_history_storage->setData($this->_shell->history);
		}
	}
	
	/**
	 * Restores the terminal to the previously saved state.
	 *
	 * @return void
	 */
	public function restoreTerminal()
	{
		system("stty '" . trim($this->_tty_saved) . "'");
	}

	/**
	 * Provides autocompletion for the given text.
	 *
	 * @param string $hint Current non-completed text string
	 *
	 * @return string Autocomplete matches
	 */
	public function autoCompleteText($hint)
	{

		$last_word = mb_substr($hint, mb_strrpos($hint, ' ')+1);

		// $hint ends in a space - so no completion to be done
		if (empty($last_word)) {
			return array();
		}

		$tables = $this->_db->getTableNames();

		if (empty($tables)) {
			return array();
		}

		$matches = array();
		foreach ($tables as $table) {
			if (mb_strpos($table, $last_word) === 0) {
				$matches[] = mb_substr($table, mb_strlen($last_word));
			}
		}

		return $matches;
	}

	/**
	 * Gets the terminal ready for our own use (switch it to raw mode).
	 *
	 * @param bool $save_existing whether to save the existing terminal settings for
	 *                            restoring later.
	 *
	 * @return void
	 */
	private function _resetTerminal($save_existing=false)
	{

		// Save existing settings
		if ($save_existing) {
			$this->_tty_saved = `stty -g`;
		}

		// Reset terminal
		system("stty raw opost -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo isig intr undef");
	}

	/**
	 * Prints the specified number of lines from the line buffer.
	 *
	 * @param integer $n number of lines to print, or 0 to print all lines, with
	 *                pagination (default)
	 *
	 * @return void
	 */
	private function _printLines($n=0)
	{

		$lines_printed = array();

		if ($n > 0) {

			// Print a specific number of lines
			$line_buffer_len = count($this->_line_buffer);
			for ($i=0; $i<$line_buffer_len && $i<$n; $i++) {
				$line = array_shift($this->_line_buffer);
				echo $line;
				$lines_printed[] = $line;
			}

			// Return the lines printed
			return $lines_printed;

		} else {

			// Get current terminal size
			$tty_size = $this->_getTtySize();

			if (count($this->_line_buffer) < $tty_size[0]) {

				// Print all lines, if it fits on the tty
				$this->_printLines(count($this->_line_buffer));

			} else {

				// Otherwise, let's paginate...

				// Print first chunk
				$last_lines = $this->_printLines($tty_size[0]-1);
				if ($last_lines[count($last_lines)-1][mb_strlen($last_lines[count($last_lines)-1])-1] != "\n") {
					echo "\n";
				}
				echo "\033[30;47m" . "--More--" . "\033[0m";

				// Print rest of the chunks
				while (1) {

					// Stop printing chunks if the line buffer is empty
					if (!count($this->_line_buffer) > 0) {
						// Backspace the "--More--"
						TerminalDisplay::backspace(8);
						break;
					}

					// Read user input
					$c = SimpleReadline::readKey();

					switch ($c) {

						// 'G' -- print rest of all the output
						case chr(71):
							TerminalDisplay::backspace(8);
							$this->_printLines(count($this->_line_buffer));
							break;

						// User wants more lines, one at a time
						case chr(10):

							// Backspace the "--More--"
							TerminalDisplay::backspace(8);

							$last_lines = $this->_printLines(1);
							if ($last_lines[count($last_lines)-1][mb_strlen($last_lines[count($last_lines)-1])-1] != "\n") {
								echo "\n";
							}
							echo "\033[30;47m" . "--More--" . "\033[0m";

							break;

						// Page down
						case chr(32):
						case chr(122):

							// Backspace the "--More--"
							TerminalDisplay::backspace(8);

							$last_lines = $this->_printLines($tty_size[0]-1);
							if ($last_lines[count($last_lines)-1][mb_strlen($last_lines[count($last_lines)-1])-1] != "\n") {
								echo "\n";
							}
							echo "\033[30;47m" . "--More--" . "\033[0m";

							break;

						// User wants to end output (ie. 'q', CTRL+C)
						case chr(3):
						case chr(113):

							// Backspace the "--More--"
							TerminalDisplay::backspace(8);

							// Clear line buffer
							$this->_clearLineBuffer();

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
	 * @param array $data array of lines to add to the buffer
	 *
	 * @return void
	 */
	private function _addToLinesBuffer($data)
	{
		// Get current terminal size
		$tty_size = $this->_getTtySize();

		// Loop through data so we can split lines at terminal size
		for ($i=0; $i<count($data); $i++) {

			// Add newlines to the end of each proper line
			$data[$i] .= "\n";

			// Split line at terminal width and add to output
			foreach (str_split($data[$i], (int)$tty_size[1]) as $line) {
				$this->_line_buffer[] = $line;
			}
		}
	}

	/**
	 * Erases everything in the line buffer.
	 *
	 * @return void
	 */
	private function _clearLineBuffer()
	{
		$this->_line_buffer = array();
	}

	/**
	 * Set an sqlclient option.
	 *
	 * @param string $option name of the option
	 * @param mixed  $value  of the option
	 *
	 * @return void
	 */
	private function _setOption($option, $value)
	{
		$this->_options[$option] = $value;
	}

	/**
	 * Get all the sqlclient options.
	 *
	 * @return array an array of all the options and their settings
	 */
	private function _getOptions()
	{
		return $this->_options;
	}

	/**
	 * Get an sqlclient option value.
	 *
	 * @param string $option the name of the option to get the current value of
	 *
	 * @return mixed the value of the specified option
	 */
	private function _getOptionValue($option)
	{

		$value = false;

		if (isset($this->_options[$option])) {

			$value = trim(strtolower($this->_options[$option]));

			switch ($value) {

				case "yes":
				case "on":
				case "1":
				case 1:
					$value = true;
					break;

				case "off":
				case "no":
				case "0":
				case 0:
					$value = false;
					break;
			}
		}

		return $value;
	}

	/**
	 * Returns the height and width of the terminal.
	 *
	 * @return array An array with two elements - number of rows and number of
	 *               columns.
	 */
	private function _getTtySize()
	{
		return explode("\n", `printf "lines\ncols" | tput -S`);
	}

	/**
	 * Performs one-time action things that need to be done when options are
	 * toggled on or off.
	 *
	 * @return void
	 */
	private function _parseOptions()
	{

		// Register autocomplete function
		if (!$this->_getOptionValue("disable-completion")) {
			$this->_shell->registerAutocompleteFunc(array($this, "autoCompleteText"));
		} else {
			$this->_shell->registerAutocompleteFunc(null);
		}

		// Set maximum history size
		$this->_history_storage->setMaxSize($this->_getOptionValue("HISTSIZE"));
	}
}
?>
