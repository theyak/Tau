<?php
/**
 * APC driver for TAU Cache module
 *
 * @Author          theyak
 * @Copyright       2014
 * @Project Page    https://github.com/theyak/Tau
 * @Dependencies    TauError
 */

if (!defined('TAU'))
{
	exit;
}

class TauCacheApc
{
	/**
	 * Reference to cache container
	 */
	private $cache;



	function __construct( $cache )
	{
		$this->cache = $cache;

		if ( ! function_exists( 'apc_store' ) )
		{
			throw new exception( "APC not installed. Can not use for cache." );
		}
	}



	/**
	 * Cache a variable in the data store
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time to live. 0 to live forever
	 */
	function set( $key, $value, $ttl = 3600 )
	{
		apc_store( $key, $value, $ttl );
	}



	/**
	 * Fetch a stored variable from the cache
	 *
	 * @param string $key
	 *
	 * @return mixed, false on error
	 */
	function get( $key )
	{
		$success = true;
		$value = apc_fetch( $key, $success );
		if ( $success )
		{
			return $value;
		}
		return false;
	}



	/**
	 * Check of a key exists in cache
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	function exists( $key )
	{
		return apc_exists( $key );
	}



	/**
	 * Remove a cached entry
	 *
	 * @param string $key
	 */
	function remove( $key )
	{
		apc_delete( $key );
	}



	/**
	 * Set note for variable. Not valid for apc.
	 *
	 * @param string $note
	 */
	function setNote( $note )
	{
		;
	}
	
	
	
	/**
	 * Get note for variable. Not valid for apc.
	 * 
	 * @param string $key
	 * @return string
	 */
	function getNote( $key )
	{
		return '';
	}



	/**
	 * Add value to key
	 *
	 * @param string $key
	 * @param number $step
	 *
	 * @return number|false
	 */
	function incr( $key, $step = 1 )
	{
		$success = true;
		$value = apc_inc( $key, $step, $success );

		if ( $success )
		{
			return $value;
		}
		return false;
	}



	/**
	 * Retrieve all, or subset thereof, the keys of object in cache
	 *
	 * @param string|boolean $prefix Prefix for keys to return
	 * @return string[]
	 */
	function keys( $prefix = false )
	{
		$list = array();

		$data = apc_cache_info( "user", false );
		if ( isset( $data[ 'cache_list' ] ) )
		{
			$length = strlen( $prefix );
			if ( is_string( $prefix ) )
			{
				foreach ( $data[ 'cache_list' ] AS $value )
				{
					if ( substr( $value[ 'info' ], 0, $length ) === $prefix )
					{
						$list[] = $value[ 'info' ];
					}
				}
			}
			else
			{
				foreach ( $data[ 'cache_list' ] AS $value )
				{
					$list[] = $value[ 'info' ];
				}
			}

		}

		return $list;
	}
}
