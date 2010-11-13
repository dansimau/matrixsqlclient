<?php
/**
 * Stores/retrieves lines in a file.
 *
 * @author Daniel Simmons <dan@dans.im>
 * @copyright Copyright (C) 2010 Daniel Simmons
 */
class HistoryStorage {

	/**
	 * @var Path and filename of the file on disk to save the data
	 */
	private $file = '';
	
	/**
	 * @var Array of the data
	 */
	private $data = array();
	
	/**
	 * @var Boolean indicating whether the data in the memory should be saved to file on destruction
	 */
	private $autosave = FALSE;

	/**
	 * Constructor
	 */
	function __construct($file, $autosave=FALSE) {
		$this->file = $file;
		$this->autosave = $autosave;
		$this->load();
	}

	/**
	 * Destructor - writes data to file if autosave flag is true
	 */
	function __destruct() {
		if ($this->autosave) {
			$this->save();
		}
	}

	/**
	 * Reads lines from the file into memory.
	 */
	function load() {

		$data = @file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if ($data === FALSE) {
			return false;
		} else {
			$this->data = $data;
			return $this->data;
		}
	}

	/**
	 * Saves contents of memory into file.
	 */
	function save() {
		return @file_put_contents($this->file, implode("\n", $this->data));
	}

	/**
	 * Returns an array of the data stored in memory.
	 */
	function get_data() {
		return $this->data;
	}

	/**
	 * Updates the array stored in the memory.
	 */
	function set_data($data) {
		if (is_array($data)) {
			$this->data = $data;
		} else {
			return false;
		}
	}
}
?>