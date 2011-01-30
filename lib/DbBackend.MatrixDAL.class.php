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

	private $macros = array();

	public function __construct() {

		$this->macros["pgsql"]["\dt"] = <<<EOF
			SELECT n.nspname as "Schema",
			  c.relname as "Name",
			  CASE c.relkind WHEN 'r' THEN 'table' WHEN 'v' THEN 'view' WHEN 'i' THEN 'index' WHEN 'S' THEN 'sequence' WHEN 's' THEN 'special' END as "Type",
			  r.rolname as "Owner"
			FROM pg_catalog.pg_class c
			     JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
			     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
			WHERE c.relkind IN ('r','')
			      AND n.nspname NOT IN ('pg_catalog', 'pg_toast')
			      AND pg_catalog.pg_table_is_visible(c.oid)
			ORDER BY 1,2;
EOF;

		$this->macros["oci"]["\dt"] = "SELECT * FROM tab;";

	}

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

		foreach ($this->macros[$this->db_type] as $pattern => $replacement) {

			$c = 0;

			$sql = str_replace($pattern, $replacement, $sql, $c);

			if ($c > 0) {
				break;
			}
		}

		// Strip semicolon from end if its Oracle
		if ($this->db_type == 'oci') {
		    $sql = mb_substr($sql, 0, mb_strlen($sql)-1);
		}

		return MatrixDAL::executeSqlAssoc($sql);
	}


	/**
	 * Gets a list of the table names for autocompletion.
	 *
	 * @returns array a list of all tables in the database
	 */
	public function getTableNames() {

		$sql = '';

		switch ($this->db_type) {

			case 'pgsql':
				$sql = <<<EOF
					SELECT
					  c.relname as "Name"
					FROM pg_catalog.pg_class c
					     JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
					     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
					WHERE c.relkind IN ('r','')
					      AND n.nspname NOT IN ('pg_catalog', 'pg_toast')
					      AND pg_catalog.pg_table_is_visible(c.oid)
					ORDER BY 1;
EOF;
				break;

			case 'oci':
				// Cheeky UNION here to allow tab completion to work for both all-upper OR
				// all-lowercase table names (only for MatrixDAL/oci, so users can be lazy)
				$sql = "SELECT tname FROM tab UNION SELECT LOWER(tname) FROM tab";
				break;
		}

		// We only know queries for pgsql and oci
		if ($sql === '') {
			$names = array();

		} else {

			try {
				$names = MatrixDAL::executeSqlAssoc($sql, 0);
			}
			catch (Exception $e) {
				$names = array();
			}
		}

		return $names;
	}

	public function matchesMacro($s) {
		return array_key_exists(trim($s), $this->macros[$this->db_type]);
	}
}
?>
