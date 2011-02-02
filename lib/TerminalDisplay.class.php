<?php
/**
 * Terminal handling functions
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @copyright 2010 Daniel Simmons
 */

class TerminalDisplay
{
	/**
	 * Moves the cursor left.
	 *
	 * @param integer $c the number of characters to move the cursor left
	 *
	 * @return void
	 */
	public static function left($c=1) {
		for ($i=0; $i<$c; $i++) echo chr(8);
	}
	
	/**
	 * Backspaces the text at the current position of the cursor
	 *
	 * @param integer $c the number of characters backspace
	 *
	 * @return void
	 */
	public static function backspace($c=1) {
		self::left($c);
		for ($i=0; $i<$c; $i++) echo ' ';
		self::left($c);
	}

	/**
	 * Returns the height and width of the terminal.
	 *
	 * @return array An array with two elements - number of rows and number of
	 *               columns.
	 */
	public function getTtySize()
	{
		return explode("\n", `printf "lines\ncols" | tput -S`);
	}}
?>