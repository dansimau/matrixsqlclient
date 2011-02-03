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
	 * @var $_db DbBackend object for the backend/database
	 */
	private $_db;

	/**
	 * @var $_historyFile Path to the file where command history will be saved
	 */
	private $_historyFile = '';

	/**
	 * @var $_tty_saved Stores stty string of saved terminal settings
	 */
	private $_tty_saved = '';

	/**
	 * @var $_shell SimpleReadline object
	 */
	private $_shell;

	/**
	 * @var $_output_buffer line output buffer
	 */
	private $_output_buffer = array();

	/**
	 * @var $_options An array with a list of options and values
	 */
	private $_options = array(
		'HISTFILE' => '~/.phpsqlc_history',
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

		$this->resetTerminal(true);

		// Instantiate database backend plugin
		$this->_db = new DbBackend($backend);

		// Instantiate/initialise stuff
		$this->_shell = new SimpleReadline();

		// Parse options; set autocomplete on/off, etc.
		$this->_parseOptions();
	}
	
	/**
	 * Destructor function - should restore terminal settings
	 */
	public function __destruct()
	{
		$this->_shell->writeHistory($this->_historyFile);
		$this->restoreTerminal();
	}

	/**
	 * Connects the db backend
	 *
	 * @param string $dsn connection string for database
	 *
	 * @return true on success or false on failure
	 */
	public function connect($dsn)
	{
		return $this->_db->connect($dsn);
	}

	/**
	 * Starts the main interactive terminal
	 *
	 * @return void
	 */
	public function run()
	{
		$this->_shell->readHistory($this->_historyFile);

		$prompt = '=# ';
		$sql = '';

		ob_start();
		echo "Welcome to matrixsqlclient (alpha";
		if (!empty($GLOBALS['rev'])) {
			echo ", rev " . $GLOBALS['rev'];
		}
		echo "), the interactive database terminal in PHP.";
		echo "\n\nYou are now connected.";
		echo "\nDatabase type: " . $this->_db->getDbType() . $this->_db->getDbVersion() . ".\n\n";
		ob_end_flush();
		
		while (1) {
		
			// Prompt for input
			$line = $this->_shell->readline($this->_db->getDbName() . $prompt);

			if ($line === "") {
				echo "\n";
				continue;
			}

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
				echo "\n";
				continue;
			}

			if (mb_strlen($line) > 0) {
				// Add this command to the history
				$this->_shell->addHistory(strtr($line, "\n", " "));
			}

			if (mb_substr(trim($line), 0, 7) == "\\timing") {

				$this->setOption("timing", !$this->_getOptionValue("timing"));

				if ($this->_getOptionValue("timing")) {
					echo "\nTiming is on.";
				} else {
					echo "\nTiming is off.";
				}

				echo "\n";
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
					$this->setOption($params[1], $params[2]);
					$this->_parseOptions();
				}

				echo "\n";
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

					echo "\n";
					continue;
				}

				// If we get an array back, it's rows
				if (is_array($source_data)) {

					// Only render the table if rows were returned
					if (!empty($source_data)) {

						// Render the table
						$table = new ArrayToTextTable($source_data);
						$table->showHeaders(true);

						$data = explode("\n", $table->render(true));
						array_pop($data);
						$this->_addToLinesBuffer($data);
					}

					// Build count summary (at end of table) and add to line buffer
					$count_str = "(" . count($source_data) . " row";
					if (count($source_data) !== 1) {
						$count_str .= "s";
					}
					$count_str .= ")";

					$this->_addToLinesBuffer(array($count_str, ""));

				// Assuming it's a string...
				} else {
					$this->_addToLinesBuffer(array($source_data));
				}

				if ($this->_getOptionValue("timing")) {
    				// Output amount of time this query took
    				$this->_addToLinesBuffer(array("", "Time: " . $this->_db->getQueryExecutionTime() . " ms"));
    			}

    			// Output the data
    			$this->_printLines();
		
				// Reset the prompt cause its a new query
				$prompt = '=# ';
				$sql = '';
		
			} elseif (mb_strlen(trim($sql)) > 0) {
				// We're in the middle of some SQL, so modify the prompt slightly to show that
				// (like psql does)
				if ((substr_count($sql, "(") > substr_count($sql, ")"))) {
					$prompt = '(# ';
				} else {
					$prompt = '-# ';
				}
				echo "\n";
			}
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
	public function autoComplete($hint)
	{
		$last_word = ltrim(mb_substr($hint, mb_strrpos($hint, ' ')));

		// Autocomplete table names after a FROM
		if (preg_match('/SELECT\s+.+\s+FROM\s+\w*$/i', $hint)) {
			$candidates = $this->_db->getTableNames();

		// Autocomplete column names after a WHERE
		} elseif (preg_match('/SELECT\s+.+\s+FROM\s+(.+?)\s+WHERE\s+\w*$/i', $hint, $table_name_search)) {
			$table_name = @$table_name_search[1];
			$candidates = $this->_db->getColumnNames($table_name);
		}

		// Nothing to autocomplete
		if (empty($candidates)) {
			return array();
		}

		// Autocomplete has options, but user hasn't begun typing yet
		if ($last_word === "") {
		    return $candidates;
		}

		// Autocomplete has options, lets narrow it down based on what the user has
		// typed already.
		$matches = array();
		foreach ($candidates as $candidate) {
			if (mb_strpos($candidate, $last_word) === 0) {
				$matches[] = $candidate;
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
	public function resetTerminal($save_existing=false)
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
			$line_buffer_len = count($this->_output_buffer);
			for ($i=0; $i<$line_buffer_len && $i<$n; $i++) {
				$line = array_shift($this->_output_buffer);
				echo $line;
				$lines_printed[] = $line;
			}

			// Return the lines printed
			return $lines_printed;

		} else {

			// Get current terminal size
			$tty_size = $this->_getTtySize();

			if (count($this->_output_buffer) < $tty_size[0]) {

				// Print all lines, if it fits on the tty
				$this->_printLines(count($this->_output_buffer));

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
					if (!count($this->_output_buffer) > 0) {
						// Backspace the "--More--"
						Terminal::backspace(8);
						break;
					}

					// Read user input
					$c = SimpleReadline::readKey();

					switch ($c) {

						// 'G' -- print rest of all the output
						case chr(71):
							Terminal::backspace(8);
							$this->_printLines(count($this->_output_buffer));
							break;

						// User wants more lines, one at a time
						case chr(10):

							// Backspace the "--More--"
							Terminal::backspace(8);

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
							Terminal::backspace(8);

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
							Terminal::backspace(8);

							// Clear line buffer
							$this->_clearLineBuffer();

							return;
							break;

						default:
							Terminal::bell();
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
				$this->_output_buffer[] = $line;
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
		$this->_output_buffer = array();
	}

	/**
	 * Set an sqlclient option.
	 *
	 * @param string $option name of the option
	 * @param mixed  $value  of the option
	 *
	 * @return void
	 */
	public function setOption($option, $value)
	{
		$this->_options[$option] = $value;
		$this->_parseOptions();
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

				case "true":
				case "yes":
				case "on":
				case "1":
					$value = true;
					break;

				case "false":
				case "off":
				case "no":
				case "0":
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
			$this->_shell->registerAutocompleteCallback(array($this, "autoComplete"));
		} else {
			$this->_shell->registerAutocompleteCallback(null);
		}

		// Set maximum history size
		$this->_shell->setHistorySize($this->_getOptionValue("HISTSIZE"));

		// Expand out tilde (~) in history filename
		if (strpos($this->_getOptionValue("HISTFILE"), "~") !== false) {
			if (isset($_ENV['HOME'])) {
				$this->_historyFile = str_replace("~", $_ENV['HOME'], $this->_getOptionValue("HISTFILE"));
			} else {
				$this->_historyFile = str_replace("~", "/tmp", $this->_getOptionValue("HISTFILE"));
			}
		} else {
			$this->_historyFile = $this->_getOptionValue("HISTFILE");
		}
	}
}
?>
