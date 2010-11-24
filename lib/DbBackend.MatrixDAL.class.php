<?php

/**
 * MatrixDAL (Squiz Matrix) backend for DbBackend.
 *
 * @author Daniel Simmons <dan@dans.im>
 * @copyright Copyright (C) 2010 Daniel Simmons
 */
class DbBackend_MatrixDAL extends DbBackendPlugin {

	/**
	 * @var $dsn DSN to connect to the database
	 */
	private $dsn = '';

	/**
	 * @var $db_type Database type: either 'oci' or 'pgsql'.
	 */
	private $db_type = '';

	/**
	 * Constructor
	 *
	 * @param $conn_string the MatrixDAL dbconf array from Squiz Matrix installation.
	 */
	public function connect($conn_string) {

		$SYSTEM_ROOT = $conn_string;

		if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
		    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
		}

		require_once $SYSTEM_ROOT.'/fudge/dev/dev.inc';
		require_once $SYSTEM_ROOT.'/core/include/general.inc';
		require_once $SYSTEM_ROOT.'/core/lib/DAL/DAL.inc';
		require_once $SYSTEM_ROOT.'/core/lib/MatrixDAL/MatrixDAL.inc';
		require_once $SYSTEM_ROOT.'/data/private/conf/db.inc';

		$this->dsn = $db_conf['db2'];
		$this->db_type = $db_conf['db2']['type'];

		// Attempt to connect
		MatrixDAL::dbConnect($this->dsn, $this->db_type);
		MatrixDAL::changeDb($this->db_type);
	}

	/**
	 * Get friendly name/identifier of the database.
	 *
	 * @return string name of the current database
	 */
	public function getDbName() {
		return $this->dsn['DSN'];
	}

	public function getDbType() {
		return $this->db_type;
	}

	public function getDbVersion() {
		return '';
	}

	/**
	 * Disconnects from the database.
	 */
	public function disconnect() {
		return true;
	}

	/**
	 * Executes the SQL query.
	 *
	 * @param $sql the SQL query to execute
	 * @param return array of the results
	 */	 
	public function execute($sql) {

		// Strip semicolon from end if its Oracle
		if ($this->db_type == 'oci') {
		    $sql = substr($sql, 0, strlen($sql)-1);
		}

		return MatrixDAL::executeSqlAssoc($sql);
	}
}
?>
