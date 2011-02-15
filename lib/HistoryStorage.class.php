<?php
/**
 * Stores an array in memory, and reads/writes that array as lines in a file.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @link      https://github.com/dansimau/matrixsqlclient
 * @copyright 2010 Daniel Simmons
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class HistoryStorage
{
	/**
	 * @var Path and filename of the file on disk to save the data
	 */
	private $_file = '';
	
	/**
	 * @var Array of the data
	 */
	private $_data = array();
	
	/**
	 * @var Boolean whether the data in the memory should be saved to file on
	 *              destruction
	 */
	private $_autosave = false;

	/**
	 * @var integer the maximum number of items that will be saved to file
	 */
	private $_maxsize = 500;

	/**
	 * Constructor
	 *
	 * @param string  $file     path and filename where history should be saved
	 * @param boolean $autosave whether to save history items to file on destruct
	 */
	function __construct($file='', $autosave=true)
	{
		$this->_file = $file;
		$this->_autosave = $autosave;
	}

	/**
	 * Destructor - writes data to file if autosave flag is true
	 */
	function __destruct()
	{
		if ($this->_autosave) {
			$this->save();
		}
	}

	/**
	 * Reads lines from the file into memory.
	 *
	 * @return mixed the data from the file, or false if the file couldn't be read
	 */
	function load()
	{
		$data = @file($this->_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if (is_array($data) === true) {
			$this->_data = $data;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Saves contents of memory into file.
	 *
	 * @return mixed number of bytes that were written to the file, or false on
	                 failure.
	 */
	function save()
	{
		while (count($this->_data) > $this->_maxsize) {
			array_shift($this->_data);
		}

		return @file_put_contents($this->_file, implode("\n", $this->_data));
	}

	/**
	 * Returns an array of the data stored in memory.
	 *
	 * @return array get all data stored in memory
	 */
	function getData()
	{
		return $this->_data;
	}

	/**
	 * Updates the array stored in the memory.
	 *
	 * @param array $data the data to store
	 *
	 * @return mixed void or false if supplied data is not an array
	 */
	function setData($data)
	{
		if (is_array($data)) {
			$this->_data = $data;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets the maximum number of lines that will be saved to file.
	 *
	 * @param integer $n number of lines
	 *
	 * @return void
	 */
	function setMaxSize($n)
	{
		$this->_maxsize = (int)$n;
	}

	/**
	 * Shows the the maximum number of lines that will be saved to file as per the
	 * current configuration.
	 *
	 * @return integer the current max number of lines that will be saved
	 */
	function getMaxSize()
	{
		return $this->_maxsize;
	}
}
?>
