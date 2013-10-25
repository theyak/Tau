<?php
/**
 * The main Tau module.
 *
 * Just include it and go.
 *
 * @Author          theyak
 * @Copyright       2012
 * @Project Page    None!
 * @Dependencies    TauError, TauFS
 * @Documentation   None!
 *
 * changelog:
 *   2013-09-29  Do not use class_exists for auto-loader. hiphop-php does not support.
 */

if (!defined('TAU'))
{
	define('TAU', true);
}

// Store the start time of when this script is executed.
// Purely for informational purposes.
Tau::$start_time = microtime(true);

// Set up autoloader so that developers do not have to spend their
// time checking if things have been included and then including if not.
spl_autoload_register('Tau::autoloadModule');

// Set the location of the root path to the Tau library, which
// unless you are doing something really funky, should be the directory
// in which this file is found.
Tau::$root_path = dirname(__FILE__) . DIRECTORY_SEPARATOR;


// Set up End of Line characters
if (Tau::isCli())
{
	Tau::$EOL = PHP_EOL;
}
else
{
	Tau::$EOL = "<br>";
}


class Tau
{
	// Path to the root of the TAU library. Generally where TAU.php is located.
	public static $root_path;

	// Start time as indicated by the time TAU is instantiated.
	public static $start_time = 0;

	// The cache, if used
	public static $cache;

	// End of line to use
	public static $EOL;

	// Directory separator, short form
	public static $DS = DIRECTORY_SEPARATOR;
	const DS = DIRECTORY_SEPARATOR;



	function __construct()
	{
	}



	/**
	 * Autoloader, called automatically by PHP when referenceing a class that
	 * is not yet loaded.
	 *
	 * @param string $module
	 */
	public static function autoloadModule($module)
	{
		self::module($module, false);
	}



	/**
	 * Load a Tau module
	 *
	 * @param string $module Name of module to load
	 * @param boolean $exception Flag indicating if exception should be thrown if unable to load module
	 * @throws Exception
	 */
	public static function module($module, $exception = true)
	{
		// hiphop-php does not support class_exists, so use this instead
		static $modules = array();

		$path = self::$root_path . 'modules' . DIRECTORY_SEPARATOR;

		// If prefix isn't Tau, then it isn't a Tau module.
		// This is most useful to help speed things up when other
		// autoloaders are defined so we don't have to go through
		// the presumably slow process of checking if the file exists or not.
		if (substr($module, 0, 3) != 'Tau')
		{
			return;
		}

		// Check if module has already been loaded. If so, do nothing.
		if (isset($modules[$module]))
		{
			return;
		}

		// Check if module exists. If it does not, throw exception as necessary
		if (!file_exists($path . $module . '.php'))
		{
			// TODO: Check a repository for class and download, save, and
			// include if available.
			if ($exception) {
				throw new Exception('Undefined module ' . $module);
			} else {
				return;
			}
		}

		include $path . $module . '.php';
		$modules[$module] = $path . $module . '.php';
	}

	/* Ultra-common utilitiy methods */


	/**
	 * Check if script is run from command prompt
	 *
	 * @return boolean
	 */
	public static function isCli()
	{
		return php_sapi_name() == 'cli';
	}



	/**
	 * Dump the contents of a variable to the display
	 *
	 * @param type $message
	 * @param type $line
	 * @param type $file
	 */
	public static function dump($message, $line='', $file='')
	{
		if (empty($file) && function_exists('debug_backtrace'))
		{
			$dbg = debug_backtrace();
			$file = $dbg[0]['file'];
			$line = $dbg[0]['line'];
			unset($dbg);
		}
		$title = array();
		if (!empty($line))
		{
			$title[] = 'Line: ' . $line;
		}
		if (!empty($file))
		{
			$title[] = 'File: ' . $file;
		}


		if (Tau::isCli())
		{
			echo empty($title) ? '' : implode(' - ', $title) . Tau::$EOL;
		}
		else
		{
			echo '<pre style="text-align:left;background-color: #ffffff; color: #000000; border: 1px; border-style: outset; padding: 5px;"><strong>' . (empty($title) ? '' : implode(' - ', $title) . '</strong><br />');
		}

		if (is_bool($message))
		{
			echo $message ? 'TRUE' : 'FALSE';
		}
		else if (empty($message))
		{
			echo is_numeric($message) ? $message : 'Empty';
		}
		else if (is_array($message) || is_object($message))
		{
			$old_handler = set_error_handler( function( $errno ) {
				;
			} );
			echo htmlspecialchars(print_r($message, true));
			set_error_handler( $old_handler );
		}
		else
		{
			echo str_replace("\t", '&nbsp; &nbsp; ', htmlspecialchars($message));
		}

		if (Tau::isCli())
		{
			echo Tau::$EOL;
		}
		else
		{
			echo '</pre>';
		}
	}
}
