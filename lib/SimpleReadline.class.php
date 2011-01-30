<?php
define('UP', chr(27).chr(91).chr(65));
define('DOWN', chr(27).chr(91).chr(66));
define('RIGHT', chr(27).chr(91).chr(67));
define('LEFT', chr(27).chr(91).chr(68));

/**
 * Alternative readline library.
 *
 * @author Daniel Simmons <dan@dans.im>
 * @copyright Copyright (C) 2010 Daniel Simmons
 */

//system("stty raw opost -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo isig intr undef");

class SimpleReadline {

	/**
	 * @var Stores the command line history.
	 */
	public $history = array();
	
	/**
	 * @var Stores a working copy the command line history.
	 */
	private $history_tmp = array();
	
	/**
	 * @var Stores the current position in the command line history.
	 */
	private $history_position = -1;
	
	/**
	 * @var Stores the data of the line the user is currently typing.
	 */
	private $buffer = '';
	
	/**
	 * @var Whether debug information is printed to the terminal.
	 */
	private $debug = FALSE;

	/**
	 * @var Stores current cursor position
	 */
	private $buffer_position = 0;

	/**
	 * @var Name of the user-defined function that is called for autocompletion
	 */
	private $callbackAutocompleteFunction = NULL;

	/**
	 * Resets buffer information and position.
	 */
	private function reset() {

		// Reset buffer
		$this->buffer = '';
		$this->buffer_position = 0;

		// Reset working history
		$this->history_tmp = $this->history;
		$this->history_tmp[] = '';
		$this->history_position = count($this->history);
	}
	
	/**
	 * Reads a single line from the user. You must add this line to the history yourself using
	 * SimpleReadline::readline_add_history().
	 *
	 * @param string prompt You may specify a string with which to prompt the user.
	 * @return Returns a single string from the user.
	 */
	public function readline($prompt=NULL) {
	
		$line = NULL;

		$this->reset();
	
		// Output prompt
		if ($prompt !== NULL) {
			echo "\n" . $prompt;
		}
		
		while (1) {
		
			$c = self::readKey();
		
			if ($this->debug) {
				echo "\ndebug: keypress:";
				for ($i=0; $i<mb_strlen($c); $i++) echo " " . ord($c[$i]);
				echo "\n";
			}
		
			switch ($c) {

				// NULL - unrecognised character
				case NULL:
					$this->bell();
					break;

				// TAB
				case chr(9):

					// If autocompletion is registered, then do it
					if ($this->callbackAutocompleteFunction !== NULL) {

						$autocomplete_text = $this->callAutocomplete($this->buffer);

						if (!empty($autocomplete_text)) {
							$this->insertIntoBuffer($autocomplete_text);
						} else {
							self::bell();
						}

					// Otherwise, TAB will insert four spaces
					} else {
						$this->insertIntoBuffer("    ");
					}

					break;

				// CTRL-A (Home) - move the cursor all the way to the left
				case chr(1):
					$this->cursorLeft($this->buffer_position);
					break;

				// CTRL-E (End) - move cursor all the way to the end
				case chr(5):
					$this->cursorRight(mb_strlen($this->buffer) - $this->buffer_position);
					break;
				
				// Line-delete - backspace from current position to beginning of line
				case chr(21):
					$this->backspace($this->buffer_position);
					break;

				// Word-delete (CTRL-W)
				case chr(23):

					// Get previous word position
					$prev_word_pos = $this->buffer_position-$this->getPreviousWordPos();

					// Delete word, unless we're at the start of the line, then bell
					if ($prev_word_pos > 0) {
						$this->backspace($this->buffer_position-$this->getPreviousWordPos());
					} else {
						$this->bell();
					}

					break;

				// CTRL-LEFT
				case chr(27) . chr(91) . chr(53) . chr(68):
					$this->cursorLeft($this->buffer_position-$this->getPreviousWordPos());
					break;

				// CTRL-RIGHT
				case chr(27) . chr(91) . chr(53) . chr(67):
					$this->cursorRight($this->getNextWordPos()-$this->buffer_position);
					break;

				case chr(3):	// CTRL-C
						$line = $this->buffer . $c;
						break;

				case chr(4):	// CTRL-D
				
					// Return current line immediately, with control character code on the end
					if (mb_strlen($this->buffer) === 0) {
						$line = $this->buffer . $c;
					}
					// Unless there is data in the buffer
					else {
						$this->bell();
					}
					break;

				case UP:
					// Move backwards in the history (or beep if we can't)
					if (!$this->historyMovePosition(-1)) $this->bell();
					break;

				case DOWN:
					// Move forward in the history (or beep if we can't)
					if (!$this->historyMovePosition(1)) $this->bell();					
					break;

				case LEFT:
					// Move left, or beep if we're already at the beginning
					if (!$this->cursorLeft()) $this->bell();
					break;

				case RIGHT:
					// Move right, or beep if we're already at the end
					if (!$this->cursorRight()) $this->bell();
					break;

				// Backspace key was pressed
				case chr(8):
				case chr(127):	// Delete

					if (!$this->backspace()) {
						self::bell();
					}
					break;
			

				// Enter key was pressed
				case chr(10):

					// Set the $line variable so we return below
					$line = $this->buffer;
					break;

				// Normal character key - add it to the buffer, and temp history
				default:
				
					// Ignore unknown control characters
					if (ord($c[0]) === 27) {
						self::bell();
						continue;
					}

					// Okay, finally... insert this character into the buffer and move on
					$this->insertIntoBuffer($c);
			}

			if ($this->debug) {
				echo "\ndebug: buffer length  : " . mb_strlen($this->buffer) . "\n";
				echo "debug: buffer contents: " . $this->buffer . "\n";
				echo "debug: buffer position: " . $this->buffer_position . "\n";
				
				echo "\ndebug: history: position: " . $this->history_position . "\n";
				echo "debug: history: item: " . $this->history_tmp[$this->history_position] . "\n";
			}

			// If line has been set, we're ready to do something with this command
			if ($line !== NULL) {
			
				// Firstly check for and process internal SimpleReadline commands
				if ($this->processInternalCommand(trim($line))) {

					// Command was executed, so add it to history, reset and start again
					$this->addHistoryItem($line);
					$line = NULL;
					$this->reset();
				}

				// Remove temp history item
				array_pop($this->history_tmp);

				return $line;
			}
		}
	}

	private function insertIntoBuffer($c) {

		// If the cursor is in the middle of the line...
		if ($this->buffer_position < mb_strlen($this->buffer)) {

			$head = mb_substr($this->buffer, 0, $this->buffer_position);
			$tail = mb_substr($this->buffer, $this->buffer_position, mb_strlen($this->buffer));

			ob_start();
			echo $c . $tail;
			TerminalDisplay::left(mb_strlen($tail));
			$this->buffer = $head . $c . $tail;
			ob_end_flush();

		} else {

			// Otherwise just append/echo it don't worry about the other stuff
			$this->buffer .= $c;
			echo $c;	// User's terminal must take care of multibyte characters
		}

		$this->buffer_position = $this->buffer_position + mb_strlen($c);
		$this->history_tmp[$this->history_position] = $this->buffer;
	}

	private function processInternalCommand($command) {

		// debug command
		if (mb_substr($command, 0, 6) === "\debug") {

			if ($this->debug) {
				echo "\ndebug mode off.\n";
				$this->debug = FALSE;

			} else {

				echo "\ndebug mode on.\n";
				$this->debug = TRUE;
			}
			
			return true;
		}
		
		// history command
		elseif (mb_substr($command, 0, 2) === "\h") {

			echo "\n\n";

			// Print history
			for ($i=0; $i<count($this->history); $i++) {
				echo $i+1 . ". " . $this->history[$i] . "\n";
			}

			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * Returns data from a keypress. This will either be a single character, or a set of control
	 * characters.
	 *
	 * @return Returns a string containing a character or set of control characters.
	 */
	public static function readKey() {
	
		$buffer = NULL;
		$key = NULL;

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
			if (self::isValidChar($buffer)) {
				return $buffer;
			}

			// Safeguard in case isValidChar() fails - UTF-8 characters will never be
			// more than 4 bytes. Something's gone wrong, so return NULL
			//
			if (strlen($buffer) > 4) {
				return NULL;
			}
		}
	}

	/**
	 * Checks a sequence of bytes and returns whether or not that sequence form a
	 * valid character under the current encoding.
	 */
	public static function isValidChar($sequence) {

		$encoding = mb_internal_encoding();

		// Check for bad byte stream
		if (mb_check_encoding($sequence) === FALSE) {
			return FALSE;
		}

		// Check for bad byte sequence
		$fs = $encoding == 'UTF-8' ? 'UTF-32' : $encoding;
		$ts = $encoding == 'UTF-32' ? 'UTF-8' : $encoding;

		if ($sequence !== mb_convert_encoding(mb_convert_encoding($sequence, $fs, $ts), $ts, $fs)) {
			return FALSE;
		}

		return TRUE;
	}
	
	/**
	 * Adds a line to the command line history.
	 *
	 * @param string The line to be added in the history.
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public function addHistoryItem($line) {
		return ($this->history[] = trim($line));
	}

	/**
	 * Alias of addHistoryItem for historical purposes.
	 */	
	public function readline_add_history($line) {
		return $this->addHistoryItem($line);
	}

	/**
	 * Move up or down in the history.
	 *
	 * @param Integer specifying how many places to move up/down in the history
	 */
	private function historyMovePosition($n) {

		// Check we can actually move this far
		if (!array_key_exists($this->history_position + $n, $this->history_tmp)) {
		
			return false;

		} else {

			ob_start();

			// Clear current line
			$this->cursorRight(mb_strlen($this->buffer) - $this->buffer_position);
			$this->backspace($this->buffer_position);

			// Move forward/back n number of positions
			$this->history_position = $this->history_position + $n;
	
			// Print history item and set buffer
			echo $this->history_tmp[$this->history_position];
			$this->buffer = $this->history_tmp[$this->history_position];
			$this->buffer_position = mb_strlen($this->buffer);

			ob_end_flush();

			return true;
		}

	}
	
	/**
	 * Updates the current history item with new data from buffer
	 */
	private function history_item_modified() {
		$this->history_tmp[$this->history_position] = $this->buffer;
	}

	/**
	 * Moves the cursor left.
	 *
	 * @param The number of characters left to move the cursor.
	 */
	private function cursorLeft($n=1) {

		// Move cursor left if we can
		if ($this->buffer_position > 0) {

			$this->buffer_position = $this->buffer_position - $n;
			TerminalDisplay::left($n);

			return true;

		} else {
			return false;
		}
	}
	
	/**
	 * Move cursor to the right.
	 *
	 * @param Number of characters to the right to move the cursor.
	 * @return boolean Whether or not the cursor was able to be moved to the right
	 */
	private function cursorRight($n=1) {

		if ($this->buffer_position < mb_strlen($this->buffer)) {

			for ($i=0; $i<$n; $i++) {
				echo mb_substr($this->buffer, $this->buffer_position, 1);
				$this->buffer_position++;
			}

			return true;

		} else {

			// Return false if the cursor is already at the end of the line
			return false;
		}
	}

	/**
	 * Backspaces characters.
	 *
	 * @param int count The number of characters to backspace.
	 */
	private function backspace($n=1) {

		if ($this->buffer_position < $n) {
		
			// We can't backspace this far
			return false;

		}

		ob_start();

		for ($i=0; $i<$n; $i++) {
			if ($this->buffer_position < mb_strlen($this->buffer)) {
	
				$head = mb_substr($this->buffer, 0, $this->buffer_position);
				$tail = mb_substr($this->buffer, $this->buffer_position, mb_strlen($this->buffer));
				
				TerminalDisplay::backspace();
				echo $tail . ' ';
				TerminalDisplay::left(mb_strlen($tail)+1);
				
				// Update buffer
				$this->buffer = mb_substr($head, 0, mb_strlen($head)-1) . $tail;
			}
			else {
	
				// Just backspace one char
				$this->buffer = mb_substr($this->buffer, 0, mb_strlen($this->buffer)-1);
				TerminalDisplay::backspace();
			}
	
			$this->buffer_position--;
		}

		ob_end_flush();

		return true;
	}

	/**
	 * Returns the buffer position of the previous word, based on current buffer position.
	 *
	 * @return integer the position of the first character of the previous word
	 */
	private function getPreviousWordPos() {

		$temp_str = mb_substr($this->buffer, 0, $this->buffer_position);

		// Remove trailing spaces on the end
		$temp_str = rtrim($temp_str);

		// Get first reverse matching space
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
	private function getNextWordPos() {

		$temp_str = mb_substr($this->buffer, $this->buffer_position, mb_strlen($this->buffer));

		// Store length, so we can calculate how many spaces are trimmed in the next step
		$temp_str_len = mb_strlen($temp_str);

		// Trim spaces from the beginning
		$temp_str = ltrim($temp_str);

		// Trimmed spaces
		$trimmed_spaces = $temp_str_len - mb_strlen($temp_str);

		// Get first matching space
		$next_word_pos = mb_strpos($temp_str, ' ');

		// If there is no matching space, we're at the end of the string
		if ($next_word_pos === FALSE) {
			$next_word_pos = mb_strlen($this->buffer);
		} else {
			$next_word_pos = $this->buffer_position + $trimmed_spaces + $next_word_pos;
		}

		return $next_word_pos;
	}

	/**
	 * Make the screen beep.
	 */
	public static function bell() {
		echo chr(7);
	}

	/**
	 * Registers the function that will be called when TAB is pressed on the prompt:
	 * function takes one parameter, the "hint", and returns the extra text to be
	 * added to the current line
	 *
	 * @param $f callback the function to call for autocompletion
	 */
	public function registerAutocompleteFunc($f) {
		$this->callbackAutocompleteFunction = $f;
	}

	/**
	 * Calls user-defined autocomplete function to complete the current string.
	 */
	public function callAutocomplete($hint) {

		if ($this->callbackAutocompleteFunction === NULL) {
			return FALSE;
		}

		// Get available string tail matches
		$matches = call_user_func($this->callbackAutocompleteFunction, $hint);

		if (empty($matches)) {
			return FALSE;
		}

		// If there's only one match, return it, along with a space on the end
		if (count($matches) === 1) {
			return $matches[0] . " ";
		}

		// At the moment, we only support in-line autocompletion
		if ($matches[0] === "") {
			return FALSE;
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

		return $finalAutocompleteString;
	}
}

class TerminalDisplay {

	public static function left($count=1) {
		for ($i=0; $i<$count; $i++) echo chr(8);
	}
	
	public static function backspace($count=1) {
		self::left($count);
		for ($i=0; $i<$count; $i++) echo ' ';
		self::left($count);
	}
}

function sortArrayByLength($a,$b){
	return count($a)-count($b);
}
?>
