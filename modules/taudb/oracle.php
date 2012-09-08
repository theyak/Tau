<?php
/**
 * Oracle driver for TAU Database module
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

class TauDbOracle
{
	/**
	 * Reference to database container
	 */
	private $db;

	function __construct($db, $dbuser, $dbpass, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;

		throw new Exception("Oracle database driver not available.");
	}

}