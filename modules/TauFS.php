<?php
/**
 * File System For Tau
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    None!
 * @Documentation   None!
 */

// TODO: Read through scandir comments, might be better routines there

if (!defined('TAU'))
{
	exit;
}

class TauFS
{
	public static $exclude = array('.', '..');

	public static function dir($dir)
	{
		$dir = rtrim($dir, '/');
		$dir = rtrim($dir, '\\');

		$result = array();

		$dh = opendir($dir);

		if (($dh = opendir($dir)))
		{
			while ($file = readdir($dh))
			{
				if (!in_array($file, self::$exclude))
				{
					if (!empty($file))
					{
						$result[] = $file;
					}
				}
			}
		}

		return $result;
	}

	public static function rdir($dir, &$files = array())
	{
		$dir = rtrim($dir, '/');
		$dir = rtrim($dir, '\\');

		if (($dh = opendir($dir)))
		{
			while ($file = readdir($dh))
			{
				if (!in_array($file, self::$exclude))
				{
					$file = $dir . DIRECTORY_SEPARATOR . $file;
					if (is_dir($file))
					{
						array_merge($files, self::rdir($file, $files));
					}
					else
					{
						$files[] = $file;
					}
				}
			}
			closedir($dh);
		}

		return $files;
	}

	public static function mkdir($dir, $permissions = 0744, $recursive = true)
	{
		if (!is_dir($dir))
		{
			mkdir($dir, $permissions, $recursive);
		}
	}
}