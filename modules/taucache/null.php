<?php
/**
 * File driver for TAU Cache module
 *
 * @Author          theyak
 * @Copyright       2015
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauCacheFile
{
	/**
	 * Reference to cache container
	 */
	private $cache;


	function __construct($cache)
	{
		$this->cache = $cache;
	}

	function connect($args)
	{
		return false;
	}
	

	function set($key, $value)
	{
		return false;
	}
	
	function get($key, $value)
	{
		return false;
	}
	
	function exists($key)
	{
		return false;
	}

	function remove($key)
	{
		return false;
	}

	function getNote($key)
	{
		return '';
	}
	
	function setNote($node)
	{
		return false;
	}

	function incr($key)
	{
		return false;
	}
	
	function decr($key)
	{
		return false;
	}
}
