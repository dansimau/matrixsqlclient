<?php
/**
 * matrixsqlclient.php - Interactive database terminal in PHP.
 *
 * dsimmons@squiz.co.uk
 * 2010-11-12 (rev 2)
 *
 */

// Get command line params
$SYSTEM_ROOT = (isset($_SERVER['argv'][1])) ? $_SERVER['argv'][1] : '';
if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
}

require_once(dirname(__FILE__) . '/lib/MatrixSqlTerminal.class.php');

error_reporting(E_ALL);

// Run the terminal
$matrixSqlTerminal = new MatrixSqlTerminal();
$matrixSqlTerminal->run();


require_once(dirname(__FILE__) . '/SimpleReadline.class.php');
require_once(dirname(__FILE__) . '/ArrayToTextTable.class.php');
//require_once $SYSTEM_ROOT.'/fudge/dev/dev.inc';
//require_once $SYSTEM_ROOT.'/core/include/general.inc';
//require_once $SYSTEM_ROOT.'/core/lib/DAL/DAL.inc';
require_once $SYSTEM_ROOT.'/core/lib/MatrixDAL/MatrixDAL.inc';
require_once $SYSTEM_ROOT.'/data/private/conf/db.inc';

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
			echo "\n" . $prompt;
		}
		
		while (1) {
		
			$c = self::readKey();
		
			if ($this->debug) echo "\ndebug: keypress: " . ord($c) . "\n";
		
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
					if (!$this->cursorLeft()) $this->bell();
					break;

				case RIGHT:
					// Move right, or beep if we're already at the end
					if (!$this->cursorRight()) $this->bell();
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
				if (substr(trim($line), 0, 6) == "\debug") {

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
						echo "\n" . $prompt;
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
						echo "\n" . $prompt;
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
		return $this->addHistoryItem($line);
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
	   		$this->cursorRight($this->buffer_position);
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
		elseif ($this->buffer_position < strlen($this->buffer)) {

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
    	
		// Updated temp history item
    	$this->historyItemModified();
			
		return true;
	}

	/**
	 * Make the screen beep.
	 */
	private function bell() {
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

/**
 * Array to Text Table Generation Class
 *
 * @author Tony Landis <tony@tonylandis.com>
 * @link http://www.tonylandis.com/
 * @copyright Copyright (C) 2006-2009 Tony Landis
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class ArrayToTextTable
{
    /** 
     * @var array The array for processing
     */
    private $rows;

    /** 
     * @var int The column width settings
     */
    private $cs = array();

    /**
     * @var int The Row lines settings
     */
    private $rs = array();

    /**
     * @var int The Column index of keys
     */
    private $keys = array();

    /**
     * @var int Max Column Height (returns)
     */
    private $mH = 2;

    /**
     * @var int Max Row Width (chars)
     */
    private $mW = 30;

    private $head  = false;
    private $pcen  = "+";
    private $prow  = "-";
    private $pcol  = "|";
    
    
    /** Prepare array into textual format
     *
     * @param array $rows The input array
     * @param bool $head Show heading
     * @param int $maxWidth Max Column Height (returns)
     * @param int $maxHeight Max Row Width (chars)
     */
    public function ArrayToTextTable($rows)
    {
        $this->rows =& $rows;
        $this->cs=array();
        $this->rs=array();
 
        if(!$xc = count($this->rows)) return false; 
        $this->keys = array_keys($this->rows[0]);
        $columns = count($this->keys);
        
        for($x=0; $x<$xc; $x++)
            for($y=0; $y<$columns; $y++)    
                $this->setMax($x, $y, $this->rows[$x][$this->keys[$y]]);
    }
    
    /**
     * Show the headers using the key values of the array for the titles
     * 
     * @param bool $bool
     */
    public function showHeaders($bool)
    {
       if($bool) $this->setHeading(); 
    } 
    
    /**
     * Set the maximum width (number of characters) per column before truncating
     * 
     * @param int $maxWidth
     */
    public function setMaxWidth($maxWidth)
    {
        $this->mW = (int) $maxWidth;
    }
    
    /**
     * Set the maximum height (number of lines) per row before truncating
     * 
     * @param int $maxHeight
     */
    public function setMaxHeight($maxHeight)
    {
        $this->mH = (int) $maxHeight;
    }
    
    /**
     * Prints the data to a text table
     *
     * @param bool $return Set to 'true' to return text rather than printing
     * @return mixed
     */
    public function render($return=false)
    {
        if($return) ob_start(null, 0, true); 
  
        $this->printLine();
        $this->printHeading();
        
        $rc = count($this->rows);
        for($i=0; $i<$rc; $i++) $this->printRow($i);
        
        $this->printLine(false);

        if($return) {
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        }
    }

    private function setHeading()
    {
        $data = array();  
        foreach($this->keys as $colKey => $value)
        { 
            $this->setMax(false, $colKey, $value);
            $data[$colKey] = $value;
        }
        if(!is_array($data)) return false;
        $this->head = $data;
    }

    private function printLine($nl=true)
    {
        print $this->pcen;
        foreach($this->cs as $key => $val)
            print $this->prow .
                str_pad('', $val, $this->prow, STR_PAD_RIGHT) .
                $this->prow .
                $this->pcen;
        if($nl) print "\n";
    }

    private function printHeading()
    {
        if(!is_array($this->head)) return false;

        print $this->pcol;
        foreach($this->cs as $key => $val)
            print ' '.
                str_pad($this->head[$key], $val, ' ', STR_PAD_BOTH) .
                ' ' .
                $this->pcol;

        print "\n";
        $this->printLine();
    }

    private function printRow($rowKey)
    {
        // loop through each line
        for($line=1; $line <= $this->rs[$rowKey]; $line++)
        {
            print $this->pcol;  
            for($colKey=0; $colKey < count($this->keys); $colKey++)
            { 
                print " ";
                print str_pad(substr($this->rows[$rowKey][$this->keys[$colKey]], ($this->mW * ($line-1)), $this->mW), $this->cs[$colKey], ' ', STR_PAD_RIGHT);
                print " " . $this->pcol;          
            }  
            print "\n";
        }
    }

    private function setMax($rowKey, $colKey, &$colVal)
    { 
        $w = mb_strlen($colVal);
        $h = 1;
        if($w > $this->mW)
        {
            $h = ceil($w % $this->mW);
            if($h > $this->mH) $h=$this->mH;
            $w = $this->mW;
        }
 
        if(!isset($this->cs[$colKey]) || $this->cs[$colKey] < $w)
            $this->cs[$colKey] = $w;

        if($rowKey !== false && (!isset($this->rs[$rowKey]) || $this->rs[$rowKey] < $h))
            $this->rs[$rowKey] = $h;
    }
}

?>
