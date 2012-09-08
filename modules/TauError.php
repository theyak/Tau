<?php
/**
 * Error module for TAU
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

class TauError
{
	public static function fatal($message, $extended = false)
	{
		echo '<html style="background-color:#a77">';
		echo '<head>';
		echo '<title>Error</title>';
		echo '</head>';
		echo '<body>';
		echo '<div style="margin:1em;padding:1em;background-color:#ffe;border:1px solid black;">';
		echo $message;
		if ($extended)
		{
			echo TauError::debug_backtrace();
		}
		echo '</div>';
		echo '</body>';
		echo '</html>';
		exit;
	}

	public static function debug_backtrace()
	{
		$msg = '<pre>';
		$trace = debug_backtrace();
		for ($i = 1; $i < sizeof($trace); $i++)
		{
			$msg .= $trace[$i]['file'] . ':' . $trace[$i]['line'] . Tau::$EOL;
			if (isset($trace[$i]['class']))
			{
				$msg .= $trace[$i]['class'] . ':';
			}
			$msg .= $trace[$i]['function'] . '()' . Tau::$EOL . Tau::$EOL;
		}
		$msg .= '</pre>';

		return $msg;
	}
}