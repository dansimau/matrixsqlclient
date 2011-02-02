<?php
/**
 * MatrixDAL (Squiz Matrix) backend for DbBackend.
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @copyright 2010 Daniel Simmons
 */
class DbBackend_MatrixDAL extends DbBackendPlugin
{
	/**
	 * @var $_dsn DSN to connect to the database
	 */
	private $_dsn = '';

	/**
	 * @var $_db_type Database type: either 'oci' or 'pgsql'.
	 */
	private $_db_type = '';

	/**
	 * @var $_macros Stores an array of macros (shorthand commands).
	 */
	private $_macros = array();

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Define macros
		$this->_macros = array(

			"pgsql" => array(

				"\dt" => "
					SELECT n.nspname as \"Schema\",
					  c.relname as \"Name\",
					  CASE c.relkind WHEN 'r' THEN 'table' WHEN 'v' THEN 'view' WHEN 'i' THEN 'index' WHEN 'S' THEN 'sequence' WHEN 's' THEN 'special' END as \"Type\",
					  r.rolname as \"Owner\"
					FROM pg_catalog.pg_class c
					     JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
					     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
					WHERE c.relkind IN ('r','')
					      AND n.nspname NOT IN ('pg_catalog', 'pg_toast')
					      AND pg_catalog.pg_table_is_visible(c.oid)
					ORDER BY 1,2;",
			),
			
			"oci" => array(
				"\dt" => "SELECT * FROM tab ORDER BY tname ASC;",
			),
		);
	}

	/**
	 * Connects to the host/database.
	 *
	 * @param string $conn_string Squiz Matrix system root.
	 *
	 * @return boolean true on success, false on failure
	 */
	public function connect($conn_string)
	{
		$SYSTEM_ROOT = $conn_string;

		if (empty($SYSTEM_ROOT) || !is_dir($SYSTEM_ROOT)) {
		    trigger_error("You need to supply the path to the System Root as the first argument" . "\n", E_USER_ERROR);
		}

		require_once $SYSTEM_ROOT.'/fudge/dev/dev.inc';
		require_once $SYSTEM_ROOT.'/core/include/general.inc';
		require_once $SYSTEM_ROOT.'/core/lib/DAL/DAL.inc';
		require_once $SYSTEM_ROOT.'/core/lib/MatrixDAL/MatrixDAL.inc';
		require_once $SYSTEM_ROOT.'/data/private/conf/db.inc';

		$this->_dsn = $db_conf['db2'];
		$this->_db_type = $db_conf['db2']['type'];

		// Attempt to connect
		MatrixDAL::dbConnect($this->_dsn, $this->_db_type);
		MatrixDAL::changeDb($this->_db_type);

		// Matrix will throw a FATAL error if it can't connect, so if we got here
		// we're all good
		return true;
	}

	/**
	 * Get the name of the current database.
	 *
	 * @return string Name of the database.
	 */
	public function getDbName()
	{
		return $this->_dsn['DSN'];
	}

	/**
	 * Get a description of the database/backend type.
	 *
	 * @return string Name of the database system.
	 */
	public function getDbType()
	{
		return $this->_db_type;
	}

	/**
	 * Get the version of the database/backend type.
	 *
	 * @return string Version of the database system.
	 */
	public function getDbVersion()
	{
		return '';
	}

	/**
	 * Disconnect from the database/host.
	 *
	 * @return boolean true on success, false on failure
	 */
	public function disconnect()
	{
		return true;
	}

	/**
	 * Execute the specified SQL/commands on the database.
	 *
	 * @param string $sql The SQL/command to send to the database.
	 *
	 * @return mixed string or array of returned data, or false on failure
	 */
	public function execute($sql)
	{

		// Check/execute macros
		foreach ($this->_macros[$this->_db_type] as $pattern => $replacement) {
			$c = 0;
			$sql = str_replace($pattern, $replacement, $sql, $c);
			if ($c > 0) {
				break;
			}
		}

		// Strip semicolon from end if its Oracle
		if ($this->_db_type == 'oci') {
		    $sql = mb_substr($sql, 0, mb_strlen($sql)-1);
		}

		return MatrixDAL::executeSqlAssoc($sql);
	}

	/**
	 * Get a list of the available tables on the current database. Used for
	 * autocomplete.
	 *
	 * @return array List of table names.
	 */
	public function getTableNames()
	{
		$sql = '';

		switch ($this->_db_type) {

			case 'pgsql':
				$sql = <<<EOF
					-- phpsqlc: tab-completion: table-names
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

	/**
	 * Get a list of the available columns on the specified table. Used for
	 * autocomplete.
	 *
	 * @param string $table Name of the table
	 *
	 * @return array List of column names
	 */
	public function getColumnNames($table)
	{
		$sql = '';

		switch ($this->_db_type) {

			case 'oci':
				// Cheeky UNION here to allow tab completion to work for both all-upper OR
				// all-lowercase table names (only for MatrixDAL/oci, so users can be lazy)
				$sql = "SELECT column_name FROM all_tab_columns WHERE table_name = " . mb_strtoupper(MatrixDAL::quote($table)) . " UNION " .
				       "SELECT LOWER(column_name) FROM all_tab_columns WHERE table_name = " . mb_strtoupper(MatrixDAL::quote($table));
				break;

			case 'pgsql':
				$sql = <<<EOF
					-- phpsqlc: tab-completion: column-names
					SELECT a.attname FROM pg_catalog.pg_attribute a
					WHERE a.attrelid IN (
					    SELECT c.oid
					    FROM pg_catalog.pg_class c
					         LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
					    WHERE c.relname = '$table' AND pg_catalog.pg_table_is_visible(c.oid)
					) AND a.attnum > 0 AND NOT a.attisdropped;
EOF;

		}

		// We only know queries for pgsql and oci
		if ($sql === '') {
			return array();
		}

		try {
		    $names = MatrixDAL::executeSqlAssoc($sql, 0);
		}
		catch (Exception $e) {
		    $names = array();
		}

		return $names;
	}

	/**
	 * Checks whether the specified command is a supported or valid macro.
	 *
	 * @param string $s Command
	 *
	 * @return boolean true if yes or false if not
	 */
	public function matchesMacro($s)
	{
		return array_key_exists(trim($s), $this->_macros[$this->_db_type]);
	}
}
?>
