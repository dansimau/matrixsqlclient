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
class SimpleReadline {

	/**
	 * @var Stores the command line history.
	 */
	private $history = array();
	
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
	 * 
	 */
	private $buffer_position = 0;
	
	/**
	 * Resets buffer information and position.
	 */
	private function reset() {

		$this->buffer = '';
		$this->buffer_position = 0;

		$this->history[] = '';
		$this->history_position = count($this->history) - 1;

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
	
		// Turn off echoing on the tty. We're going to control this.
		system("stty raw opost -olcuc -ocrnl onlcr -onocr -onlret icrnl -inlcr -echo");
		
		// Output prompt
		if ($prompt !== NULL) {
			echo NL . $prompt;
		}
		
		while (1) {
		
			$c = self::readKey();
		
			if ($this->debug) echo "\ndebug: keypress: " . ord($c) . "\n";
		
			switch ($c) {

				// Home - move the cursor all the way to the left
				case chr(1):
					$this->cursorLeft($this->buffer_position);
					break;

				// End - move cursor all the way to the end
				case chr(5):
					$this->cursorRight(strlen($this->buffer) - $this->buffer_position);
					break;
				
				// Line-delete - backspace from current position to beginning of line
				case chr(21):
					$this->backspace($this->buffer_position);
					$this->historyItemModified();
					break;

				case chr(3):	// CTRL-C
				case chr(4):	// CTRL-D
				
					// Return current line immediately, with control character code on the end
					$line = $this->buffer . $c;
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
					if (!$this->cursorMoveLeft()) $this->bell();
					break;

				case RIGHT:
					// Move right, or beep if we're already at the end
					if (!$this->cursorMoveLeft()) $this->bell();
					break;

				// Backspace key was pressed
				case chr(8):
				case chr(127):	// Delete

					if ($this->backspace()) {
						$this->historyItemModified();
					} else {
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
						$tail = $this->getBufferTail();
						echo $c . $tail;
						self::moveleft(strlen($tail));
						$this->buffer = $this->getBufferHead() . $c . $this->getBufferTail();

					} else {

						// Otherwise just echo it don't worry about the other stuff
						$this->buffer .= $c;
						echo $c;
					}

					$this->buffer_position++;
					$this->history[$this->history_position] = $this->buffer;
			}

			if ($this->debug) {
				echo "\ndebug: buffer length  : " . strlen($this->buffer) . "\n";
				echo "debug: buffer contents: " . $this->buffer . "\n";
				echo "debug: buffer position: " . $this->buffer_position . "\n";
			}
				
			// If $line has been set, return it
			if ($line !== NULL) {
			
				// Special SimpleReadline commands
				if (strpos(trim($line) == "\debug"), 0, 6) {

					if ($this->debug) {
						echo "\ndebug mode off.\n";
						$this->debug = FALSE;

					} else {

						echo "\ndebug mode on.\n";
						$this->debug = TRUE;
					}
					
					// Reset everything
					$line = NULL;
					$this->reset();

					if ($prompt !== NULL) {
						echo NL . $prompt;
					}

				} elseif (trim($line) == "\history") {
				
					echo "\n\n";

					// Print history
					for ($i=0; $i<count($this->history); $i++) {
						echo $i+1 . ". " . $this->history[$i] . "\n";
					}

					// Reset everything
					$line = NULL;
					$this->reset();

					if ($prompt !== NULL) {
						echo NL . $prompt;
					}
				}

				// Remove temp history item
				array_pop($this->history);

				// Restore original tty settings
				system('stty sane');

				return $line;
			}
		}
	}
	
	/**
	 * Returns data from a keypress. This will either be a single character, or a set of control
	 * characters.
	 *
	 * @return Returns a string containing a character or set of control characters.
	 */
	private static function readKey() {
	
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
		return $this->history[] = trim($line);
	}

	/**
	 * Alias of addHistoryItem for historical purposes.
	 */	
	public function readline_add_history($line) {
		return addHistoryItem($line);
	}

	/**
	 * Move up or down in the history.
	 *
	 * @param Integer specifying how many places to move up/down in the history
	 */
	private function historyMovePosition($n) {

		// Check we can actually move this far
		if (!array_key_exists($this->history_position + $n, $this->history)) {
		
    		return false;

    	} else {
    	
    		// Move forward/back n number of positions
    		$this->history_position = $this->history_position + $n;
	
	   		// Clear current line
	   		$this->cursorEnd();
	   		$this->backspace(strlen($this->buffer));
	
	   		// Print history item and set buffer
	   		echo $this->history[$this->history_position];
	   		$this->buffer = $this->history[$this->history_position];
	   		$this->buffer_position = strlen($this->buffer);
	   		
	   		return true;
    	}

	}
	
	/**
	 * Updates the current history item with new data from buffer
	 */
	private function historyItemModified() {
		$this->history[$this->history_position] = $this->buffer;
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
			for ($i=0; $i<$n; $i++) echo chr(8);

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

		} else {

			// Move cursor left
			$this->cursorLeft($n);
			
			// Remove proceeding character from buffer
			$this->buffer = (substr($this->buffer, 0, $this->buffer_position)) . (substr($this->buffer, $this->buffer_position + $n, strlen($buffer)));
			
			// Reprint the rest of the line + one blank char
			$taillen = strlen($this->buffer) - $this->buffer_position;
			$this->cursorRight($taillen);
			echo " ";		
			
			// Go back to where we were
			$this->cursorLeft($taillen);

			return true;
	}

	/**
	 * Make the screen beep.
	 */
	private function bell() {
		echo chr(7);
	}

}
?>