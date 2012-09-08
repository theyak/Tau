	<?php
/**
 * file: TauDbServer.php
 *
 * author:
 *   levans (evans@artofproblemsolving.com)
 *
 * description:
 *
 *
 * changelog:
 *   0.0.1  Aug 24, 2012  Created
 */

if (!defined('TAU'))
{
	exit;
}

class TauDbServer
{
	public $host;
	public $port;
	public $username;
	public $password;
	public $database;
	public $connection = false;
}