<?php
/**
 * File System For Tau
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    None!
 * @Documentation   None!
 */


if (!defined('TAU'))
{
	exit;
}

class TauFS
{
	public static $exclude = array('.', '..');


	/**
	 * Retrive listing of files in directory
	 *
	 * @param string $dir Path to retrived directory of, defaults to current directory
	 * @param string $pattern Filename pattern to search
     *
	 * @return string[] List of files
	 */
	public static function dir( $dir = '.', $pattern = '*' )
	{
		$dir = rtrim( $dir, '/' );
		$dir = rtrim( $dir, '\\' );

		$result = array();

		if ( ( $dh = opendir( $dir ) ) !== false )
		{
			while ( ( $file = readdir( $dh ) ) !== false )
			{
				if ( ! in_array( $file, self::$exclude ) && !empty( $file ) )
				{
					if ( $pattern && fnmatch( $pattern, basename( $file ) ) )
					{
						$result[] = $file;
					}
				}
			}
		}

		return $result;
	}



	/**
	 * Recursively retrive listing of files in directory
	 *
	 * @param string $dir Path to retrived directory of, defaults to current directory
	 * @param string $pattern Filename pattern to search
	 *
	 * @return string[] List of files
	 */
	public static function rdir( $dir = '.', $pattern = '*', &$files = array() )
	{
		$dir = rtrim( $dir, '/' );
		$dir = rtrim( $dir, '\\' );

		if ( ( $dh = opendir( $dir ) ) !== false )
		{
			while ( ( $file = readdir( $dh ) ) !== false )
			{
				if ( ! in_array( $file, self::$exclude ) )
				{
					$file = $dir . DIRECTORY_SEPARATOR . $file;
					if ( is_dir( $file ) )
					{
						array_merge( $files, self::rdir( $file, $pattern, $files ) );
					}
					else if ( fnmatch( $pattern, basename( $file ) ) )
					{
						$files[] = $file;
					}
				}
			}
			closedir( $dh );
		}

		return $files;
	}



	/**
	 * Make a directory. This is just a simple wrapper for mkdir
	 *
	 * @param string $dir Directory to create
	 * @param octal $permission Permissions to give directory
	 * @param boolean $recursive Whether it should create all paths in directory
	 */
	public static function mkdir($dir, $permissions = 0744, $recursive = true)
	{
		if ( ! is_dir( $dir ) )
		{
			mkdir( $dir, $permissions, $recursive );
		}
	}
}
