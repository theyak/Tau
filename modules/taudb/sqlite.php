<?php
/**
 * SQLite driver for TAU Database module
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauDbSqlite
{
	/**
	 * Reference to database container
	 */
	private $db;

	function __construct($db, $dbuser, $dbpass, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;
	}

	public function freeResult($resultSet = null) {
		// Not applicapble
	} 	
	
	public function now() {
		return 'NOW()';
	}
	
	public function stringify($value) {
		return $this->stringTick . sqlite_escape_string((string)$value)  . $this->stringTick;
	}
	
	public function emptyFiled() {
		return $this->stringTick . $this->stringTick;
	}
	
}