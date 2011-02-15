<?php
/**
 * Abstract class for DB backend plugins.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @link      https://github.com/dansimau/matrixsqlclient
 * @copyright 2010 Daniel Simmons
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
abstract class DbBackendPlugin
{
	/**
	 * Connects to the host/database.
	 *
	 * @param string $conn_string Connection string/DSN for connecting to the
	 *                            database.
	 *
	 * @return boolean true on success, false on failure
	 */
	abstract public function connect($conn_string);

	/**
	 * Disconnect from the database/host.
	 *
	 * @return boolean true on success, false on failure
	 */
	abstract public function disconnect();

	/**
	 * Execute the specified SQL/commands on the database.
	 *
	 * @param string $sql The SQL/command to send to the database.
	 *
	 * @return mixed string or array of returned data, or false on failure
	 */
	abstract public function execute($sql);

	/**
	 * Get the name of the current database.
	 *
	 * @return string Name of the database.
	 */
	abstract public function getDbName();

	/**
	 * Get a description of the database/backend type.
	 *
	 * @return string Name of the database system.
	 */
	abstract public function getDbType();

	/**
	 * Get the version of the database/backend type.
	 *
	 * @return string Version of the database system.
	 */
	abstract public function getDbVersion();

	/**
	 * Get a list of the available tables on the current database. Used for
	 * autocomplete.
	 *
	 * @return array List of table names.
	 */
	abstract public function getTableNames();

	/**
	 * Get a list of the available columns on the specified table. Used for
	 * autocomplete.
	 *
	 * @param string $table Name of the table
	 *
	 * @return array List of column names
	 */
	abstract public function getColumnNames($table);

	/**
	 * Checks whether the specified command is a supported or valid macro.
	 *
	 * @param string $s Command
	 *
	 * @return boolean true if yes or false if not
	 */
	abstract public function matchesMacro($s);
}
?>
