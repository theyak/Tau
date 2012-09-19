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
	public static $content_type = 'application/json';

	/**
	 *  Send JSON
	 *  @param mixed $data Data to send
	 */
	public static function send_json($data)
	{
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="files.json"');
		header('X-Content-Type-Options: nosniff');
		header('Content-Type: ' . TauAjax::$content_type);
	    echo json_encode($data);
		exit;
	}

	public static function text_mode()
	{
		self::$content_type = 'text/plain';
	}

	/**
	 *  Send URL formated parameters. Very useful for REST-like applications
	 */
	static public function send_query($parameters, $delimiter = '&')
	{
		echo http_build_query($parameters, '', $delimiter);
		exit;
	}

	/**
	 * Respond to Ajax with a status of Alert
	 *
	 * @param string $message
	 * @param $data Optional data to send along with JSON result
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
	 *  Success status
	 *
	 *  @param array $data Additional parameters to send with success result
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
	 *  Error status
	 *
	 *  @param string $errmsg Error message
	 *  @param integer $errno Error number, default 0
	 *  @param array $data Additional parameters to send with error result
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
	 *  Compact for html
	 *
	 *  @param string &$message
	 */
	static public function compact(&$message)
	{
		$message =  str_replace(array("\r\n", "\n", "\r", "\t"), '', $message);
	}
}
