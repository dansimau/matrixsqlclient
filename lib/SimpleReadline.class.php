<?php
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
				for ($i=0; $i<strlen($c); $i++) echo " " . ord($c[$i]);
				echo "\n";
			}
		
			switch ($c) {

				// CTRL-A (Home) - move the cursor all the way to the left
				case chr(1):
					$this->cursorLeft($this->buffer_position);
					break;

				// CTRL-E (End) - move cursor all the way to the end
				case chr(5):
					$this->cursorRight(strlen($this->buffer) - $this->buffer_position);
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
					if (strlen($this->buffer) === 0) {
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
					if (preg_match('/[[:cntrl:]]+/', $c)) {
						self::bell();
						continue;
					}
				
					if ($this->buffer_position < strlen($this->buffer)) {
					
						// If the cursor is in the middle of the line...
						$head = substr($this->buffer, 0, $this->buffer_position);
						$tail = substr($this->buffer, $this->buffer_position, strlen($this->buffer));
						
						ob_start();
						echo $c . $tail;
						TerminalDisplay::left(strlen($tail));
						$this->buffer = $head . $c . $tail;
						ob_end_flush();

					} else {

						// Otherwise just echo it don't worry about the other stuff
						$this->buffer .= $c;
						echo $c;
					}

					$this->buffer_position++;
					$this->history_tmp[$this->history_position] = $this->buffer;
			}

			if ($this->debug) {
				echo "\ndebug: buffer length  : " . strlen($this->buffer) . "\n";
				echo "debug: buffer contents: " . $this->buffer . "\n";
				echo "debug: buffer position: " . $this->buffer_position . "\n";
				
				echo "\ndebug: history: position: " . $this->history_position . "\n";
				echo "debug: history: item: " . $this->history_tmp[$this->history_position] . "\n";
				var_dump($this->history_tmp);
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

	private function processInternalCommand($command) {

		// debug command
		if (substr($command, 0, 2) === "\d") {

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
		elseif (substr($command, 0, 2) === "\h") {

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
			
			// If we received ESC character, we're expecting a control character (more
			// chars) so create a buffer and read another character
			if (ord($c) === 27) {

				$buffer = $c;
				continue;

			// If first character was ESC, then this char is part of control code. Add
			// this character to the buffer and read another character
			} elseif (ord($c) === 91 && ord($buffer[0]) === 27 && strlen($buffer) === 1) {

				$buffer .= $c;
				continue;

			// If third character is a number, then keep going (get another character)
			} elseif (strlen($buffer) === 2 && ord($buffer[0]) === 27 && ord($buffer[1]) === 91 && ord($c) >= 30 && ord($c) <= 57) {

				$buffer .= $c;
				continue;

			// If we've got the right two characters in the buffer, send them all
			} elseif (ord($buffer[0]) === 27 && ord($buffer[1]) === 91) {

				$key = $buffer . $c;

			} else {

				// In most cases, just return the character, unless a control character
				// was preceding it.
				$key = $c;
			}

			// Should should have been set above, so return it
			return $key;
		}
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
	   		$this->cursorRight(strlen($this->buffer) - $this->buffer_position);
	   		$this->backspace($this->buffer_position);

    		// Move forward/back n number of positions
    		$this->history_position = $this->history_position + $n;
	
	   		// Print history item and set buffer
			echo $this->history_tmp[$this->history_position];
	   		$this->buffer = $this->history_tmp[$this->history_position];
	   		$this->buffer_position = strlen($this->buffer);
	   		
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

		if ($this->buffer_position < strlen($this->buffer)) {

			for ($i=0; $i<$n; $i++) {
				echo $this->buffer[$this->buffer_position];
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

		for ($i=0; $i<$n; $i++) {
			if ($this->buffer_position < strlen($this->buffer)) {
	
				$head = substr($this->buffer, 0, $this->buffer_position);
				$tail = substr($this->buffer, $this->buffer_position, strlen($this->buffer));
				
				TerminalDisplay::backspace();
				echo $tail . ' ';
				TerminalDisplay::left(strlen($tail)+1);
				
				// Update buffer
				$this->buffer = substr($head, 0, strlen($head)-1) . $tail;
			}
			else {
	
				// Just backspace one char
				$this->buffer = substr($this->buffer, 0, strlen($this->buffer)-1);
				TerminalDisplay::backspace();
			}
	
			$this->buffer_position--;
		}
    	
		return true;
	}

	/**
	 * Returns the buffer position of the previous word, based on current buffer position.
	 *
	 * @return integer the position of the first character of the previous word
	 */
	private function getPreviousWordPos() {

		$temp_str = substr($this->buffer, 0, $this->buffer_position);

		// Remove trailing spaces on the end
		$temp_str = rtrim($temp_str);

	    // Get first reverse matching space
	    $prev_word_pos = strrpos($temp_str, ' ');

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

		$temp_str = substr($this->buffer, $this->buffer_position, strlen($this->buffer));

		// Store length, so we can calculate how many spaces are trimmed in the next step
		$temp_str_len = strlen($temp_str);

		// Trim spaces from the beginning
		$temp_str = ltrim($temp_str);

		// Trimmed spaces
		$trimmed_spaces = $temp_str_len - strlen($temp_str);

	    // Get first matching space
	    $next_word_pos = strpos($temp_str, ' ');

	    // If there is no matching space, we're at the end of the string
	    if ($next_word_pos === FALSE) {
			$next_word_pos = strlen($this->buffer);
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
?>