<?php
/**
 * Postgres driver for TAU Database module
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

class TauDbPostgres
{
	/**
	 * Reference to database container
	 */
	private $db;

	function __construct($db, $dbuser, $dbpass, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;

		throw new Exception("Postgres database driver not available.");
	}

}