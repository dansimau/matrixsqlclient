<?php
/**
 * DbBackend - wrapper for backend plugins.
 *
 * @author Daniel Simmons <dan@dans.im>
 * @copyright Copyright (C) 2010 Daniel Simmons
 */
class DbBackend {

	protected $_executionTime;

	/**
	 * @var $backend instantiated plugin class
	 */
	private $_backend;

	/**
	 * Constructor
	 *
	 * @param $pluginName name of the db backend plugin to use
	 */
	public function __construct($pluginName) {

		$backend = NULL;
		$pluginName = 'DbBackend_' . $pluginName;

		if (class_exists($pluginName)) {
			$backend = new $pluginName;
		}

		if (is_null($backend) || !get_parent_class($pluginName) == 'DbBackendPlugin') {
			echo("Cannot find valid DbBackendPlugin class \"" . $pluginName . "\".");
			exit(20);
		}

		$this->_backend = $backend;
	}

	/**
	 * Calls plugin to disconnect and reconnect to specified backend.
	 *
	 * @param $dsn plugin's backend to connect to
	 * @return bool whether the connection was successful
	 */
	public function connect($dsn) {
		$this->disconnect();
		return $this->_backend->connect($dsn);
	}

	/**
	 * Calls plugin to return a friendly name/identifier for the connected backend.
	 *
	 * @return string friendly name/identifier for the currently connected backend
	 */
	public function getDbName() {
		return $this->_backend->getDbName();
	}

	public function getDbType() {
		return $this->_backend->getDbType();
	}

	public function getDbVersion() {
		return $this->_backend->getDbVersion();
	}

	/**
	 * Calls plugin to disconnect from backend.
	 *
	 * @return bool if the disconnect was a success
	 */
	public function disconnect() {
		return $this->_backend->disconnect();
	}

	/**
	 * Calls plugin to execute specified query.
	 *
	 * @return mixed data array of results, or false if the query was invalid
	 */
	public function execute($sql) {

		$query_start_time = microtime(true);
		$result = $this->_backend->execute($sql);
		$query_end_time = microtime(true);

		$this->_executionTime = $query_end_time - $query_start_time;

		return $result;
	}

	/**
	 * Returns the execution time of the last query.
	 */
	public function getQueryExecutionTime() {
		return round($this->_executionTime * 1000, 3);
	}

	public function getTableNames() {
		return $this->_backend->getTableNames();
	}

	public function getColumnNames($table) {
		return $this->_backend->getColumnNames($table);
	}

	/**
	 * Checks to see if the current line matches an internal command.
	 */
	public function matchesMacro($s) {
		return $this->_backend->matchesMacro($s);
	}
}

abstract class DbBackendPlugin {
	abstract public function connect($conn_string);
	abstract public function getDbName();
	abstract public function disconnect();
	abstract public function execute($sql);
	abstract public function getTableNames();
	abstract public function matchesMacro($s);
}
?>
