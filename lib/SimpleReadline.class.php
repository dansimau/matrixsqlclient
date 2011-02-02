<?php
function sortArrayByLength($a,$b)
{
	return count($a)-count($b);
}

define('UP', chr(27).chr(91).chr(65));
define('DOWN', chr(27).chr(91).chr(66));
define('RIGHT', chr(27).chr(91).chr(67));
define('LEFT', chr(27).chr(91).chr(68));

//system("stty raw opost -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo isig intr undef");

/**
 * Alternative readline library.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @copyright 2010 Daniel Simmons
 */
class SimpleReadline
{
	/**
	 * @var Stores the command line history.
	 */
	private $_history = array();
	
	/**
	 * @var Stores a working copy the command line history.
	 */
	private $_history_tmp = array();
	
	/**
	 * @var Stores the current position in the command line history.
	 */
	private $_history_position = -1;
	
	/**
	 * @var $history_stage HistoryStorage class that saves command history to a file
	 */
	private $_history_storage = null;

	/**
	 * @var Stores the data of the line the user is currently typing.
	 */
	private $_buffer = '';
	
	/**
	 * @var Stores current cursor position
	 */
	private $_buffer_position = 0;

	/**
	 * @var Name of the user-defined function that is called for autocompletion
	 */
	private $_autocomplete_callback = null;

	/**
	 * @var Prompt prefix
	 */
	private $_prompt = null;

	/**
	 * @var number of times TAB has been pressed since last autocomplete
	 */
	private $_autocompleteTabPressCount = 0;

	/**
	 * Adds a line to the command line history.
	 *
	 * @param string $line Line to be added in the history.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function addHistory($line)
	{
		return ($this->_history[] = trim($line));
	}

	/**
	 * Reads a command history from a file.
	 *
	 * @param string $filename Path to the filename containing the command history.
	 *
	 * @return boolean true on success or false on failure
	 */
	public function readHistory($filename)
	{
		$this->_history_storage = null;
		$this->_history_storage = new HistoryStorage($filename);

		if ($this->_history_storage->load()) {
			$this->_history = $this->_history_storage->getData();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets the maximum number of history items that will be saved to file.
	 *
	 * @param integer $c Maximum number of history items to save to file.
	 *
	 * @return true on success or false on failure
	 */
	public function setHistorySize($c)
	{
		if (is_integer($c)) {
			$this->_history_storage->setMaxSize($c);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reads a single line from the user.
	 *
	 * @param string $prompt You may specify a string with which to prompt the user.
	 *
	 * @return Returns a single string from the user.
	 */
	public function readline($prompt=null)
	{
		$line = null;

		$this->_reset();
	
		// Output prompt

		if ($prompt !== null) {
			$this->_prompt = $prompt;
			echo "\n" . $prompt;
		}
		
		while (1) {
		
			$c = self::_readKey();
		
			switch ($c) {

				// Unrecognised character
				case null:
					Terminal::bell();
					break;

				// TAB
				case chr(9):

					// If autocompletion is registered, then do it
					if ($this->_autocomplete_callback !== null) {

						$autocomplete_text = $this->_doAutocomplete($this->_buffer);

						if (!empty($autocomplete_text)) {
							$this->_insert($autocomplete_text);
						} else {
							Terminal::bell();
						}

					// Otherwise, TAB will insert spaces
					} else {
						$this->_insert("        ");
					}

					break;

				// CTRL-A (Home) - move the cursor all the way to the left
				case chr(1):
					$this->_cursorLeft($this->_buffer_position);
					break;

				// CTRL-E (End) - move cursor all the way to the end
				case chr(5):
					$this->_cursorRight(mb_strlen($this->_buffer) - $this->_buffer_position);
					break;
				
				// Line-delete - backspace from current position to beginning of line
				case chr(21):
					$this->_backspace($this->_buffer_position);
					break;

				// Word-delete (CTRL-W)
				case chr(23):

					// Get previous word position
					$prev_word_pos = $this->_buffer_position-$this->_getPreviousWordPos();

					// Delete word, unless we're at the start of the line, then bell
					if ($prev_word_pos > 0) {
						$this->_backspace($this->_buffer_position-$this->_getPreviousWordPos());
					} else {
						Terminal::bell();
					}

					break;

				// CTRL-LEFT
				case chr(27) . chr(91) . chr(53) . chr(68):
					$this->_cursorLeft($this->_buffer_position-$this->_getPreviousWordPos());
					break;

				// CTRL-RIGHT
				case chr(27) . chr(91) . chr(53) . chr(67):
					$this->_cursorRight($this->_getNextWordPos()-$this->_buffer_position);
					break;

				// CTRL-C
				case chr(3):
						$line = $this->_buffer . $c;
						break;

				// CTRL-D
				case chr(4):
				
					// Return current line immediately on CTRL-D
					if (mb_strlen($this->_buffer) === 0) {
						$line = $this->_buffer . $c;
					} else {
						Terminal::bell();
					}
					break;

				case UP:
					// Move backwards in the history
					if (!$this->_historyMovePosition(-1)) {
						Terminal::bell();
					}
					break;

				case DOWN:
					// Move forward in the history
					if (!$this->_historyMovePosition(1)) {
						Terminal::bell();
					}
					break;

				case LEFT:
					// Move left, or beep if we're already at the beginning
					if (!$this->_cursorLeft()) {
						Terminal::bell();
					}
					break;

				case RIGHT:
					// Move right, or beep if we're already at the end
					if (!$this->_cursorRight()) {
						Terminal::bell();
					}
					break;

				// Backspace key
				case chr(8):
				// Delete
				case chr(127):

					if (!$this->_backspace()) {
						Terminal::bell();
					}
					break;

				// Enter key
				case chr(10):

					// Set the $line variable so we return below
					$line = $this->_buffer;
					break;

				// Normal character key
				default:
				
					// Ignore unknown control characters
					if (ord($c[0]) === 27) {
						Terminal::bell();
						continue;
					}

					// Insert this character into the buffer and move on
					$this->_insert($c);
			}

			// If line has been set, we're ready to do something with this command
			if ($line !== null) {
			
				// Firstly check for internal commands
				if ($this->_runInternalCommand(trim($line))) {

					// It it was an internal command, don't return, just reset and pretend
					// nothing happened...
					$this->addHistory($line);
					$line = null;
					$this->_reset();
				}

				// Remove temp history item
				array_pop($this->_history_tmp);

				return $line;
			}
		}
	}

	/**
	 * Registers the function that will be called when TAB is pressed on the prompt:
	 * function takes one parameter, the "hint", and returns the extra text to be
	 * added to the current line
	 *
	 * @param callback $f callback the function to call for autocompletion
	 *
	 * @return void
	 */
	public function registerAutocompleteCallback($f)
	{
		$this->_autocomplete_callback = $f;
	}

	/**
	 * Writes the command history to a file.
	 *
	 * @param string $filename Path to the saved file.
	 *
	 * @return boolean true on success or false on failure
	 */
	public function writeHistory($filename)
	{
		$this->_history_storage->setData($this->_history);

		if ($this->_history_storage->save() !== false) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Backspaces characters.
	 *
	 * @param int $n The number of characters to backspace.
	 *
	 * @return true on success, false on failure
	 */
	private function _backspace($n=1)
	{
		if ($this->_buffer_position < $n) {
			// We can't backspace this far
			return false;
		}

		ob_start();

		for ($i=0; $i<$n; $i++) {
			if ($this->_buffer_position < mb_strlen($this->_buffer)) {
	
				$head = mb_substr($this->_buffer, 0, $this->_buffer_position);
				$tail = mb_substr($this->_buffer, $this->_buffer_position, mb_strlen($this->_buffer));
				
				Terminal::backspace();
				echo $tail . ' ';
				Terminal::left(mb_strlen($tail)+1);
				
				// Update buffer
				$this->_buffer = mb_substr($head, 0, mb_strlen($head)-1) . $tail;

			} else {
	
				// Just backspace one char
				$this->_buffer = mb_substr($this->_buffer, 0, mb_strlen($this->_buffer)-1);
				Terminal::backspace();
			}
	
			$this->_buffer_position--;
		}

		ob_end_flush();

		return true;
	}

	/**
	 * Move up or down in the history.
	 *
	 * @param integer $n specifying how many places to move up/down in the history
	 *
	 * @return true on success, false on failure
	 */
	private function _historyMovePosition($n)
	{
		// Check we can actually move this far
		if (!array_key_exists($this->_history_position + $n, $this->_history_tmp)) {
		
			return false;

		} else {

			ob_start();

			// Clear current line
			$this->_cursorRight(mb_strlen($this->_buffer) - $this->_buffer_position);
			$this->_backspace($this->_buffer_position);

			// Move forward/back n number of positions
			$this->_history_position = $this->_history_position + $n;
	
			// Print history item and set buffer
			echo $this->_history_tmp[$this->_history_position];
			$this->_buffer = $this->_history_tmp[$this->_history_position];
			$this->_buffer_position = mb_strlen($this->_buffer);

			ob_end_flush();

			return true;
		}

	}
	
	/**
	 * Inserts the specified text/character into the current position in the buffer
	 *
	 * @param string $c the character or string to insert
	 *
	 * @return void
	 */
	private function _insert($c)
	{
		// If the cursor is in the middle of the line...
		if ($this->_buffer_position < mb_strlen($this->_buffer)) {

			$head = mb_substr($this->_buffer, 0, $this->_buffer_position);
			$tail = mb_substr($this->_buffer, $this->_buffer_position, mb_strlen($this->_buffer));

			ob_start();
			echo $c . $tail;
			Terminal::left(mb_strlen($tail));
			$this->_buffer = $head . $c . $tail;
			ob_end_flush();

		} else {

			// Otherwise just append/echo it don't worry about the other stuff
			$this->_buffer .= $c;
			echo $c;	// User's terminal must take care of multibyte characters
		}

		$this->_buffer_position = $this->_buffer_position + mb_strlen($c);
		$this->_history_tmp[$this->_history_position] = $this->_buffer;
	}

	/**
	 * Checks a sequence of bytes and returns whether or not that sequence form a
	 * valid character under the current encoding.
	 *
	 * @param string $sequence string of bytes to check
	 *
	 * @return boolean true if string is valid, false if not
	 */
	private static function _isValidChar($sequence)
	{

		$encoding = mb_internal_encoding();

		// Check for bad byte stream
		if (mb_check_encoding($sequence) === false) {
			return false;
		}

		// Check for bad byte sequence
		$fs = $encoding == 'UTF-8' ? 'UTF-32' : $encoding;
		$ts = $encoding == 'UTF-32' ? 'UTF-8' : $encoding;

		if ($sequence !== mb_convert_encoding(mb_convert_encoding($sequence, $fs, $ts), $ts, $fs)) {
			return false;
		}

		return true;
	}
	
	/**
	 * Moves the cursor left.
	 *
	 * @param integer $n The number of characters left to move the cursor.
	 *
	 * @return boolean true on success or false on failure
	 */
	private function _cursorLeft($n=1)
	{
		// Move cursor left if we can
		if ($this->_buffer_position > 0) {

			$this->_buffer_position = $this->_buffer_position - $n;
			Terminal::left($n);

			return true;

		} else {
			return false;
		}
	}
	
	/**
	 * Move cursor to the right.
	 *
	 * @param integer $n Number of characters to the right to move the cursor.
	 *
	 * @return boolean Whether or not the cursor was able to be moved to the right
	 */
	private function _cursorRight($n=1)
	{
		if ($this->_buffer_position < mb_strlen($this->_buffer)) {

			for ($i=0; $i<$n; $i++) {
				echo mb_substr($this->_buffer, $this->_buffer_position, 1);
				$this->_buffer_position++;
			}

			return true;

		} else {

			// Return false if the cursor is already at the end of the line
			return false;
		}
	}

	/**
	 * Calls user-defined autocomplete function to complete the current string.
	 *
	 * @param string $hint Line of typed text to pass to the callback function.
	 *
	 * @return mixed returns the partial text to complete, or false if nothing
	 */
	private function _doAutocomplete($hint)
	{
		if ($this->_autocomplete_callback === null) {
			return false;
		}

		$candidates = call_user_func($this->_autocomplete_callback, $hint);

		if (empty($candidates)) {
			return false;
		}

		$last_word = mb_substr($hint, mb_strrpos($hint, ' ')+1);

		/* If the last word is nothing '', then it means the user hasn't started off
		   the autocomplete (given a hint) at all. We don't do inline autocomplete in
		   this case. */
		if ($last_word === '') {
			$this->_showAutoCompleteOptions($candidates);
			return false;
		}

		/* Otherwise, the user has started typing a word, and we want to autocomplete
		   it in-line as much as possible before showing possible options. */
		$matches = array();
		foreach ($candidates as $match) {
			if (mb_strpos($match, $last_word) === 0) {
				$matches[] = mb_substr($match, mb_strlen($last_word));
			}
		}

		if (empty($matches)) {
			return false;
		}

		// If there's only one match, return it, along with a space on the end
		if (count($matches) === 1) {
			return $matches[0] . " ";
		}

		// Otherwise, let's complete as many common letters as we can...

		$finalAutocompleteString = '';

		// Explode each character of each match into it's own array
		$candidate_map = array();
		foreach ($matches as $match) {
			$candidate_map[] = preg_split('/(?<!^)(?!$)/u', $match); // preg_split here for multibyte chars
		}

		// Sort matches by length, shortest first
		usort($candidate_map, 'sortArrayByLength');

		for ($i=0; $i<count($candidate_map[0]); $i++) {	// "Best match" can't be longer than shortest candidate

			$chars = array();

			// Get all the letters at position $i from all candidates
			foreach ($candidate_map as &$candidate) {
				$chars[] = $candidate[$i];
			}

			// Check if they are all the same letter
			$chars_uniq = array_unique($chars);
			if (count($chars_uniq) === 1) {
				$finalAutocompleteString .= $chars_uniq[0];
			}
		}

		if ($finalAutocompleteString === '') {
			$this->_showAutoCompleteOptions($candidates);
		}

		return $finalAutocompleteString;
	}

	/**
	 * Returns the buffer position of the previous word, based on current buffer position.
	 *
	 * @return integer the position of the first character of the previous word
	 */
	private function _getPreviousWordPos()
	{
		$temp_str = mb_substr($this->_buffer, 0, $this->_buffer_position);

		// Remove trailing spaces on the end
		$temp_str = rtrim($temp_str);

		// Get first reverse matching space
		if (mb_strlen($temp_str) === 0) {
			return 0;
		}
		$prev_word_pos = mb_strrpos($temp_str, ' ');

		// Add one, which is the beginning of the previous word (unless we're at the beginning of the line)
		if ($prev_word_pos > 0) {
			$prev_word_pos++;
		}

		return $prev_word_pos;
	}

	/**
	 * Returns the buffer position of the next word, based on current buffer position.
	 *
	 * @return integer the position of the first character of the next word
	 */
	private function _getNextWordPos()
	{
		$temp_str = mb_substr($this->_buffer, $this->_buffer_position, mb_strlen($this->_buffer));

		// Store length, so we can calculate how many spaces are trimmed in the next step
		$temp_str_len = mb_strlen($temp_str);

		// Trim spaces from the beginning
		$temp_str = ltrim($temp_str);

		// Trimmed spaces
		$trimmed_spaces = $temp_str_len - mb_strlen($temp_str);

		// Get first matching space
		$next_word_pos = mb_strpos($temp_str, ' ');

		// If there is no matching space, we're at the end of the string
		if ($next_word_pos === false) {
			$next_word_pos = mb_strlen($this->_buffer);
		} else {
			$next_word_pos = $this->_buffer_position + $trimmed_spaces + $next_word_pos;
		}

		return $next_word_pos;
	}

	/**
	 * Returns data from a keypress. This will either be a single character, or a set of control
	 * characters.
	 *
	 * @return Returns a string containing a character or set of control characters.
	 */
	private static function _readKey()
	{
		$buffer = null;
		$key = null;

		while (1) {

			$c = fgetc(STDIN);

			$buffer .= $c;

			// Handle control characters
			if (ord($buffer[0]) === 27) {

				if ((strlen($buffer) === 1) && (ord($c) === 27)) {
					continue;
				} elseif ((strlen($buffer) === 2) && (ord($c) === 91)) {
					continue;
				} elseif (strlen($buffer) === 3 && ord($c) >= 30 && ord($c) <= 57) {
					continue;
				} else {
					return $buffer;
				}
			}

			// Handle other characters and multibyte characters
			if (self::_isValidChar($buffer)) {
				return $buffer;
			}

			// Safeguard in case isValidChar() fails - UTF-8 characters will never be
			// more than 4 bytes. Something's gone wrong, so return null
			if (strlen($buffer) > 4) {
				return null;
			}
		}
	}

	/**
	 * Resets buffer information and position.
	 *
	 * @return void
	 */
	private function _reset()
	{
		// Reset buffer
		$this->_buffer = '';
		$this->_buffer_position = 0;

		// Reset working history
		$this->_history_tmp = $this->_history;
		$this->_history_tmp[] = '';
		$this->_history_position = count($this->_history);
	}
	
	/**
	 * Parses the given string and runs any internal commands.
	 *
	 * @param string $command the input string
	 *
	 * @return boolean whether an internal command matched and was run
	 */
	private function _runInternalCommand($command)
	{
		// history command
		if (mb_substr($command, 0, 5) === "\hist") {

			echo "\n\n";

			// Print history
			for ($i=0; $i<count($this->_history); $i++) {
				$p = strlen((string)count($this->_history)) + 1;
				printf("%" . $p . "s  %s\n", $i+1, $this->_history[$i]);
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Outputs a visual list of the autocomplete candidates.
	 *
	 * @param array $options an array of the candidates
	 *
	 * @return void
	 */
	private function _showAutoCompleteOptions($options)
	{
		// TAB must be pressed twice to show autocomplete options
		if (!$this->_autocompleteTabPressCount > 0) {
			$this->_autocompleteTabPressCount++;
			return;
		} else {
			$this->_autocompleteTabPressCount = 0;
		}

		$optionMaxChars = 0;

		// Get length of the longest match (for spacing)
		foreach ($options as $option) {
			if (mb_strlen($option)+2 > $optionMaxChars) {
				$optionMaxChars = mb_strlen($option) + 2; // +2 spaces to pad with
			}
		}

		// Get tty width
		$ttySize = Terminal::getTtySize();
		$ttyChars = $ttySize[1];

		// Calculate number of lines required
		$linesRequired = ceil((count($options)*$optionMaxChars) / $ttyChars);

		// Calculate number of items per line
		$itemsPerLine = floor($ttyChars / $optionMaxChars);

		for ($i=0; $i < count($options); $i++) {
			if ($i % $itemsPerLine === 0) {
				echo "\n";
			}

			printf("%-" . $optionMaxChars . "s", $options[$i]);
		}
		echo "\n";
		echo $this->_prompt . $this->_buffer;
	}
}
?>
