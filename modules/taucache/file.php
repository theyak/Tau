<?php
/**
 * File driver for TAU Cache module
 *
 * @Author          theyak
 * @Copyright       2015
 * @Project Page    https://github.com/theyak/Tau
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

	/**
	 * Path of cache data files
	 */
	private $path;


	/**
	 * Previously loaded cache data
	 * @var array()
	 */
	private static $cache_results = array();


	function __construct( $cache, $opts )
	{
		$this->cache = $cache;
		if ( isset( $opts[ 'path' ] ) )
		{
			$this->setPath( $opts[ 'path' ] );
		}
		else
		{
			$this->setPath( dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cachedata' );
		}
	}



	/**
	 * Set the path where cache files are stored
	 *
	 * @param string $path
	 */
	function setPath( $path )
	{
		if ( ! in_array( substr( $path, -1 ), array( '/', '\\' ) ) ) {
			$path .= DIRECTORY_SEPARATOR;
		}

		$this->path = $path;
	}



	/**
	 * Cache a variable in the data store
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time to live. 0 to live forever
	 */
	function set( $key, $value, $ttl = 3600, $opts = array() )
	{
		// Check if key has a directory name
		$dirname = dirname( $key );
		if ( $dirname != '.' && ! empty( $dirname ) )
		{
			$path = $this->path . $dirname . DIRECTORY_SEPARATOR;
			if ( ! is_dir( $path ) )
			{
				mkdir( $path, 0744, true );
			}
		}

		$file = $this->path . $key . '.php';
		$data = serialize( $value );
		$lines = array(
			"<?php die('This is an automatically generated file from TauCache. Do not edit'); ?>",
			'1.0',
			$ttl ? time() + $ttl : 0,
		);

		if ( isset( $opts[ 'notes' ] ) )
		{
			$lines[] = trim( preg_replace('/[\n\r\s\t]+/', ' ', $opts[ 'notes' ] ) );
		}
		else
		{
			$lines[] = '';
		}

		$lines[] = strlen( $data );
		$lines[] = $data;

		file_put_contents( $file, implode( "\n", $lines ) );
	}



	/**
	 * Fetch a stored variable from the cache
	 *
	 * @param string $key
	 *
	 * @return mixed, false on error
	 */
	function get($key)
	{
		if ( isset( static::$cache_results[ $key ] ) )
		{
			return static::$cache_results[ $key ][ 'data' ];
		}

		$file = $this->path . $key . '.php';
		if ( is_file( $file ) )
		{
			// This sometimes fails, how?!
			$f = @fopen( $file, 'rb' );
			if ( $f )
			{
				// Read header line
				$header = fgets( $f );

				if ( ! $header )
				{
					return false;
				}
				$version = trim( fgets( $f ) );

				$expires = intval( fgets( $f ) );

				$data = false;
				if ( $expires >= time() || $expires <= 0 )
				{
					$notes = fgets( $f );
					if ( preg_match( '/^[0-9]+$/', $notes ) )
					{
						$length = (int) $notes;
					}
					else
					{
						$length = (int) fgets( $f );
					}
					$data = fread( $f, $length );
					fclose( $f );
					if ( strlen( $data ) === $length )
					{
						// Disable E_NOTICE for bad data
						$old = error_reporting(E_ALL & ~E_NOTICE);
						$data = unserialize($data);
						error_reporting($old);
						if ($data) {
							static::$cache_results[$key] = [
								'data' => $data,
								'notes' => $notes,
							];

							return $data;
						}
					}
					$this->remove($file);
				}
				else
				{
					fclose($f);
					$this->remove($file);
				}
			}
		}
		return false;
	}



	/**
	 * Get note for a cache entry
	 *
	 * @param string $key
	 * @return string
	 */
	public function getNote($key)
	{
		if ( ! isset( static::$cache_results[ $key ] ) )
		{
			$result = $this->get( $key );
		}

		if ( isset( static::$cache_results[ $key ] ) )
		{
			return static::$cache_results[ $key ][ 'notes' ];
		}

		return '';
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
		$file = $this->path . $key . '.php';
		if ( is_file( $file ) )
		{
			$f = fopen( $file, 'rt');
			if ($f)
			{
				// Read header line
				$header = fgets( $f );

				$version = trim( fgets( $f ) );

				$expires = intval( fgets( $f ) );

				fclose($f);

				if ( $expires >= time() || $expires <= 0 )
				{
					return true;
				}
				else
				{
					$this->remove($file);
				}
			}
		}

		return false;
	}



	/**
	 * Remove a cached entry
	 *
	 * @param string $key
	 */
	function remove( $key )
	{
		if ( is_file( $this->path . $key . '.php' ) )
		{
			$old = error_reporting(E_ALL & ~E_WARNING);
			unlink($this->path . $key . '.php');
			error_reporting($old);
		}
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
		$value = $this->get( $key );
		if ( $value )
		{
			$value = $value + $step;
			$this->set( $key, $value );
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
		$length = strlen( $this->path );

		$files = TauFS::rdir( $this->path );

		$files = array_map( function ( $value ) use( $length ) {
			return substr( $value, $length );
		}, $files );


		// Only get files of specified prefix - should change to regex
		if ( is_string( $prefix ) )
		{
			$length = strlen( $prefix );
			foreach ( $files AS $key => $value )
			{
				if ( substr( $value, 0, $length ) !== $prefix )
				{
					unset( $files[ $key ] );
				}
			}
		}


		// Make sure it's a valid cache file (sorta)
		foreach ( $files AS $key => $value )
		{
			if ( substr( $value, -4 ) !== '.php' )
			{
				unset( $files[ $key ] );
			}
			else
			{
				$files[ $key ] = substr( $value, 0, strlen( $value ) - 4 );
			}
		}

		return $files;
	}
}
