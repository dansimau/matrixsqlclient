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
	private $backend;

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

		$this->backend = $backend;
	}

	/**
	 * Calls plugin to disconnect and reconnect to specified backend.
	 *
	 * @param $dsn plugin's backend to connect to
	 * @return bool whether the connection was successful
	 */
	public function connect($dsn) {
		$this->disconnect();
		return $this->backend->connect($dsn);
	}

	/**
	 * Calls plugin to return a friendly name/identifier for the connected backend.
	 *
	 * @return string friendly name/identifier for the currently connected backend
	 */
	public function getDbName() {
		return $this->backend->getDbName();
	}

	public function getDbType() {
		return $this->backend->getDbType();
	}

	public function getDbVersion() {
		return $this->backend->getDbVersion();
	}

	/**
	 * Calls plugin to disconnect from backend.
	 *
	 * @return bool if the disconnect was a success
	 */
	public function disconnect() {
		return $this->backend->disconnect();
	}

	/**
	 * Calls plugin to execute specified query.
	 *
	 * @return mixed data array of results, or false if the query was invalid
	 */
	public function execute($sql) {

		$query_start_time = microtime(true);
		$result = $this->backend->execute($sql);
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
		return $this->backend->getTableNames();
	}
}

abstract class DbBackendPlugin {
	abstract public function connect($conn_string);
	abstract public function getDbName();
	abstract public function disconnect();
	abstract public function execute($sql);
	abstract public function getTableNames();
}
?>
