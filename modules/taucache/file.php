<?php
/**
 * File driver for TAU Cache module
 *
 * @Author          theyak
 * @Copyright       2011
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
	 * Note to store with data
	 */
	private $note;

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
	function set( $key, $value, $ttl = 3600 )
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
			$this->note,
			strlen( $data ),
			$data,
		);
		file_put_contents( $file, implode( "\n", $lines ) );
		$this->note = '';
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
		$file = $this->path . $key . '.php';
		if ( is_file( $file ) )
		{
			$f = fopen( $file, 'rt');
			if ( $f )
			{
				// Read header line
				$header = fgets( $f );

				$version = trim( fgets( $f ) );

				$expires = intval( fgets( $f ) );

				$data = false;
				if ( $expires >= time() || $expires <= 0 )
				{
					$note = trim( fgets( $f ) );
					$length = intval( fgets( $f ) );
					$data = trim( fgets( $f ) );
					fclose( $f );
					if ( strlen( $data ) == $length )
					{
						$data = unserialize( $data );
						return $data;
					}
				}
				else
				{
					fclose( $f );
					@unlink( $file );
				}
			}
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
					@unlink( $file );
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
			unlink( $this->path . $key . '.php' );
		}
	}



	/**
	 * Set note for variable.
	 *
	 * @param string $note
	 */
	function setNote($note) {
		$this->note = str_replace(array("\r", "\n"), '', $note);
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
}
