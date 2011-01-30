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
	 * @var integer the maximum number of items that will be saved to file
	 */
	private $maxsize = 500;

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

		while (count($this->data) > $this->maxsize) {
			array_shift($this->data);
		}

		return @file_put_contents($this->file, implode("\n", $this->data));
	}

	/**
	 * Returns an array of the data stored in memory.
	 */
	function getData() {
		return $this->data;
	}

	/**
	 * Updates the array stored in the memory.
	 */
	function setData($data) {
		if (is_array($data)) {
			$this->data = $data;
		} else {
			return false;
		}
	}

	/**
	 * Sets the maximum number of lines that will be saved to file.
	 */
	function setMaxSize($n) {
		$this->maxsize = (int)$n;
	}

	/**
	 * Shows the the maximum number of lines that will be saved to file as per the
	 * current configuration.
	 */
	function getMaxSize($n) {
		return $this->maxsize;
	}
}
?>