<?php
/**
 * MSSQL driver for TAU Database module
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauDbMssql
{
	/**
	 * Reference to database container
	 */
	private $db;

	function __construct($db, $dbuser, $dbpass, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;

		throw new Exception("MSSQL database driver not available.");
	}

}