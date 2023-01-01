<?php
/**
 * Ajax Module For TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauAjax
{
	/**
	 * Send JSON data. The data passed in will be converted to a JSON
	 * string appropriate for sending across the intertubes.
	 *
	 * @param  mixed $data Data to send
	 */
	public static function send_json($data)
	{
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="file.json"');
		header('X-Content-Type-Options: nosniff');
		header('Content-Type: application/json');
	    echo json_encode($data);
		exit;
	}



	/**
	 * Send text data.
	 *
	 * @param  string $string
	 */
	public static function send_string($string)
	{
		self::send_text($string);
	}



	/**
	 * Send text data.
	 *
	 * @param  string $string
	 */
	public static function send_text($string)
	{
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="file.txt"');
		header('X-Content-Type-Options: nosniff');
		header('Content-Type: text/plain');
		echo $string;
		exit;
	}



	/**
	 * Send URL formated parameters. Very useful for REST-like applications.
	 *
	 * @param array $parameters
	 * @param string $delimiter Delimiter to use for query, usually & or &amp;
	 */
	static public function send_query($parameters, $delimiter = '&')
	{
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="query.txt"');
		header('X-Content-Type-Options: nosniff');
		header('Content-Type: text/plain');
		echo http_build_query($parameters, '', $delimiter);
		exit;
	}



	/**
	 * Respond to AJAX with a status of Alert and the 'msg' field set to
	 * text to display in an alert box.
	 *
	 * @param  string $message
	 * @param  mixed $data Optional data to send along with JSON result
	 */
	static public function alert($message, $data = array())
	{
		$result = array(
			'status' => 'Alert',
			'msg' => $message,
		);

		if (sizeof($data))
		{
			$result['data'] = $data;
		}

		self::send_json($result);
	}



	/**
	 * Respond to AJAX with a status of OK. Same as TauAjax::OK().
	 *
	 * @param  array $data Additional parameters to send with success result
	 */
	public static function success($data = array())
	{
		$result = array(
			'status' => 'OK',
		);

		if (sizeof($data))
		{
			$result['data'] = $data;
		}

		self::send_json($result);
	}



	/**
	 * Respond to AJAX with a status of OK. Same as TauAjax::success().
	 *
	 * @param  array $data Additional parameters to send with success result
	 */
	public static function OK($data = array())
	{
		self::success($data);
	}



	/**
	 * Send an AJAX response with 'status' = 'Error', 'errmsg' equal to the contents
	 * of the $errmsg paramater, 'errno' equal to the contents of the $errno
	 * paramater, and 'data' equal to the contents of the $data parameter.
	 *
	 * @param  string $errmsg Error message
	 * @param  int $errno Error number, default 0
	 * @param  array $data Additional parameters to send with error result
	 */
	public static function error($errmsg, $errno = 0, $data = array())
	{
		$result = array(
			'status' => 'Error',
			'errmsg' => $errmsg,
			'errno' => $errno,
		);

		if (sizeof($data))
		{
			$result['data'] = $data;
		}

		self::send_json($result);
	}



	/**
	 * Helper function to compact for html, converting new lines, tabs, and
	 * multiple spaces to a single space.
	 *
	 * @param  string &$message
	 */
	static public function compact(&$message)
	{
		$message = str_replace(array("\r\n", "\n", "\r", "\t"), ' ', $message);
		$message = preg_replace('@\s+@', ' ', $message);
	}
}
