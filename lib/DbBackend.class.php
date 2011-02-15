<?php
/**
 * DbBackend - wrapper for backend plugins.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @link      https://github.com/dansimau/matrixsqlclient
 * @copyright 2010 Daniel Simmons
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class DbBackend
{
	/**
	 * @var $_executionTime Used to measure the time a query takes to run.
	 */
	private $_executionTime;

	/**
	 * @var $backend instantiated plugin class
	 */
	private $_backend;

	/**
	 * Constructor
	 *
	 * @param string $pluginName name of the db backend plugin to use
	 */
	public function __construct($pluginName)
	{
		$backend = null;
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
	 * @param string $dsn plugin's backend to connect to
	 *
	 * @return bool whether the connection was successful
	 */
	public function connect($dsn)
	{
		$this->disconnect();
		return $this->_backend->connect($dsn);
	}

	/**
	 * Calls plugin to return a friendly name/identifier for the connected backend.
	 *
	 * @return string friendly name/identifier for the currently connected backend
	 */
	public function getDbName()
	{
		return $this->_backend->getDbName();
	}

	/**
	 * Calls plugin to return the database type.
	 *
	 * @return string friendly name/identifier for the database backend
	 */
	public function getDbType()
	{
		return $this->_backend->getDbType();
	}

	/**
	 * Calls plugin to return a version identifier for the database backend
	 *
	 * @return string friendly version identifier for the database backend
	 */
	public function getDbVersion()
	{
		return $this->_backend->getDbVersion();
	}

	/**
	 * Calls plugin to disconnect from backend.
	 *
	 * @return bool if the disconnect was a success
	 */
	public function disconnect()
	{
		return $this->_backend->disconnect();
	}

	/**
	 * Calls plugin to execute specified query.
	 *
	 * @param string $sql the SQL to run
	 *
	 * @return mixed data array of results, or false if the query was invalid
	 */
	public function execute($sql)
	{
		$query_start_time = microtime(true);
		$result = $this->_backend->execute($sql);
		$query_end_time = microtime(true);

		$this->_executionTime = $query_end_time - $query_start_time;

		return $result;
	}

	/**
	 * Returns the execution time of the last query.
	 *
	 * @return float the time the query took to execute, in ms, to 3 decimal places
	 */
	public function getQueryExecutionTime()
	{
		return round($this->_executionTime * 1000, 3);
	}

	/**
	 * Call plugin to get a list of the available tables on the current database.
	 *
	 * @return array List of table names.
	 */
	public function getTableNames()
	{
		return $this->_backend->getTableNames();
	}

	/**
	 * Call plugin to get a list of the columns on the specified table.
	 *
	 * @param string $table Name of the table.
	 *
	 * @return array List of table names.
	 */
	public function getColumnNames($table)
	{
		return $this->_backend->getColumnNames($table);
	}

	/**
	 * Checks to see if the current line matches an internal command/macro.
	 *
	 * @param string $s Command
	 *
	 * @return boolean true if yes or false if not
	 */
	public function matchesMacro($s)
	{
		return $this->_backend->matchesMacro($s);
	}
}
?>
