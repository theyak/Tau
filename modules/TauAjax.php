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
	 *  Alert
	 *
	 *  @param string $message
	 */
	static public function alert($message)
	{
		self::send_json(array('status' =>'Alert', 'msg' => $message));
	}


	/**
	 *  Success status
	 *
	 *  @param array $options Additional parameters to send with success result
	 */
	public static function success($options = array())
	{
		self::send_json(array_merge(array('status' => 'OK'), $options));
	}

	/**
	 *  Error status
	 *
	 *  @param string $errmsg Error message
	 *  @param integer $errno Error number, default 0
	 *  @param array $options Additional parameters to send with error result
	 */
	public static function error($errmsg, $errno = 0, $options = array())
	{
		$object = array(
			'status' => 'Error',
			'errmsg' => $errmsg,
			'errno' => $errno,
		);

		self::send_json(array_merge($object, $options));
	}

	/**
	 *  Compact for html
	 *
	 *  @param string &$message
	 */
	static public function compact_for_html(&$message)
	{
		$message =  str_replace(array("\r\n", "\n", "\r", "\t"), array('', '', '', ''), $message);
	}

}
