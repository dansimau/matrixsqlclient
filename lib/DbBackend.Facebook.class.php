<?php
/**
 * Facebook (FQL) backend plugin
 *
 * @author    Daniel Simmons <dan@dans.im>
 * @link      https://github.com/dansimau/matrixsqlclient
 * @copyright 2010 Daniel Simmons
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class DbBackend_Facebook extends DbBackendPlugin
{

	const FACEBOOK_QUERY_URL = 'https://api.facebook.com/method/fql.query?query=';

	/**
	 * @var $_accessToken stores the Facebook access token for authentication
	 */
	private $_accessToken = '';

	/**
	 * Connects to the host/database.
	 *
	 * @param string $conn_string Connection string/DSN for connecting to the
	 *                            database.
	 *
	 * @return boolean true on success, false on failure
	 */
	public function connect($conn_string='')
	{
		$this->_accessToken = $conn_string;
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
		$xml = simplexml_load_file(self::FACEBOOK_QUERY_URL . $sql . '&access_token=' . $this->_accessToken);
		var_dump($xml);
		return "";
	}

	/**
	 * Get the name of the current database.
	 *
	 * @return string Name of the database.
	 */
	public function getDbName()
	{
		return "facebook";
	}

	/**
	 * Get a description of the database/backend type.
	 *
	 * @return string Name of the database system.
	 */
	public function getDbType()
	{
		return "facebook-fql";
	}

	/**
	 * Get the version of the database/backend type.
	 *
	 * @return string Version of the database system.
	 */
	public function getDbVersion()
	{
		return "1.0";
	}

	/**
	 * Get a list of the available tables on the current database. Used for
	 * autocomplete.
	 *
	 * @return array List of table names.
	 */
	public function getTableNames()
	{
		return array(
		             "album",
		             "application",
		             "checkin",
		             "comment",
		             "comments_info",
		             "connection",
		             "cookies",
		             "developer",
		             "domain",
		             "domain_admin",
		             "event",
		             "event_member",
		             "family",
		             "friend",
		             "friend_request",
		             "friendlist",
		             "friendlist_member",
		             "group",
		             "group_member",
		             "insights",
		             "like",
		             "link",
		             "link_stat",
		             "mailbox_folder",
		             "message",
		             "note",
		             "notification",
		             "object_url",
		             "page",
		             "page_admin",
		             "page_fan",
		             "permissions",
		             "permissions_info",
		             "photo",
		             "photo_tag",
		             "place",
		             "privacy",
		             "profile",
		             "standard_friend_info",
		             "standard_user_info",
		             "status",
		             "stream",
		             "stream_filter",
		             "stream_tag",
		             "thread",
		             "translation",
		             "unified_message",
		             "unified_thread",
		             "unified_thread_action",
		             "unified_thread_count",
		             "user",
		             "video",
		             "video_tag",
		             );
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
		return array();
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
		return false;
	}
}
?>
