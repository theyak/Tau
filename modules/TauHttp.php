<?php
/**
 * HTTP module for TAU
 * Makes curl more user friendly. Requires curl to run. Tried to do this without curl,
 * but it kept failing during the feof/fread loop for binary files.
 *
 * @Author          theyak
 * @Copyright       2011
 * @Depends on      curl
 * @Project Page    None!
 * @docs            None!
 *
 */

/**
 * Example Usage:
$http = new TauHttp('https://www.duckduckgo.com');
$http->setProxyHost('127.0.0.1');
$http->setProxyPort(8080);
$http->setProxyType(CURLPROXY_SOCKS5);
$http->setPostField('q', 'google');
Tau::dump($http->fetch());
 */


class TauHttp
{
	private $_url;
	private $_scheme = 'http';
	private $_host = '';
	private $_port = 80;
	private $_user = '';
	private $_pass = '';
	private $_path = '';
	private $_query = '';
	private $_fragment = '';
	private $_cookies = array();
	private $_agent = 'Tau';
	private $_post = array();
	
	/**
	 * Timeout value for connection to remote host
	 */
	private $_timeout = 15;
	
	/**
	 * Proxy host settings
	 */
	private $_proxy_host = '';
	private $_proxy_port = 8080;
	private $_proxy_name = '';
	private $_proxy_pass = '';
	private $_proxy_type = CURLPROXY_SOCKS5;
	
	
	/**
	 * Public properties
	 */
	public $info = array();
	public $responseCode = 0;
	public $responseHeaders = array();
	public $response = '';
	public $error = '';

	
	function __construct($url = null) 
	{
		if (!is_null($url))
		{
			$this->setUrl($url);
		}
	}

	function setUrl($url)
	{
		$this->_url = $url;
		$url = parse_url($url);
		if (isset($url['scheme']))    $this->_scheme = $url['scheme'];
		if (isset($url['host']))      $this->_host = $url['host'];
		if (isset($url['port']))      $this->_port = $url['port'];
		if (isset($url['user']))      $this->_user = $url['user'];
		if (isset($url['pass']))      $this->_pass = $url['pass'];
		if (isset($url['path']))      $this->_path = $url['path']; else $this->_path = '/';
		if (isset($url['query']))     $this->_query = $url['query'];
		if (isset($url['fragment']))  $this->_fragment = $url['fragment'];
	}

	public static function get($url) {
		$http = new TauHttp();
		return $http->fetch($url);
	}
	
	public static function post($url, $postFields) {
		if (!is_array($postFields)) {
			$this->error = 'Invalid post fields';
			return false;
		}
		$http = new TauHttp();
		$http->setPostField($postFields);
		return $http->fetch($url);
	}
	
	public function fetch($url = null)
	{
		if (!is_null($url)) {
			$this->setUrl($url);
		}
		
		if (!function_exists('curl_init')) {
			$this->error = 'curl not installed as php extension.';
			return;
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
		curl_setopt($ch, CURLOPT_COOKIE, $this->cookieHeader());
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		if (sizeof($this->_post))
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_post);
		}
		
		if (!empty($this->_agent))
		{
			curl_setopt($ch, CURLOPT_USERAGENT,	$this->_agent);
		}

		if (!empty($this->_proxy_host))
		{
			curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, TRUE);			
			curl_setopt($ch, CURLOPT_PROXY, $this->_proxy_host);
			curl_setopt($ch, CURLOPT_PROXYPORT, $this->_proxy_port);
			if (!empty($this->_proxy_name)) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->_proxy_name . ':' . $this->_proxy_pass);
			}
			curl_setopt($ch, CURLOPT_PROXYTYPE, $this->_proxy_type);
		}
		
		if ($this->_scheme == 'https') {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		}
		
		$result = curl_exec($ch);

		$this->info = curl_getinfo($ch);
		$this->responseCode = $this->info['http_code'];

		curl_close($ch);

		$this->responseHeaders = array();
		while (substr($result, 0, 4) == 'HTTP')
		{
			$parts = explode("\r\n\r\n", $result);
			$part = array_shift($parts);
			$lines = explode("\r\n", $part);
			$array = array('_header' => array_shift($lines));
			foreach ($lines AS $line) {
				$pos = strpos($line, ": ");
				if ($pos !== false) {
					$array[substr($line, 0, $pos)] = substr($line, $pos + 2);
				}
			}

			$this->responseHeaders[] = $array;
			$result = implode("\r\n\r\n", $parts);
		}
		$this->response = $result;

		return $this->response;
	}

	public function setUserAgent($agent)
	{
		$agents = array(
			'Firefox' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:6.0.1) Gecko/20100101 Firefox/6.0.1',
			'IE8' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0)',
			'Google' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
		);
		
		if (isset($agents[$agent]))
		{
			$this->_agent = $agents[$agent];
		}
		else
		{
			$this->_agent = $agent;
		}
	}
	
	public function setTimeout($timeout = 5)
	{
		$this->_timeout = intval($timeout);
	}
	
	public function setCookie($key, $value = null)
	{
		if (is_array($key))
		{
			foreach ($key AS $name => $value)
			{
				$this->_cookies[] = $name . '=' . $value;
			}
		}
		else
		{
			$this->_cookies[] = $key . '=' . $value;
		}
	}
	
	
	public function setPostField($key, $value = null)
	{
		if (is_array($key))
		{
			$this->_post = array_merge($this->_post, $key);
		}
		else
		{
			$this->_post[$key] = $value;
		}
	}
	
	public function setProxyHost($host) {
		$this->_proxy_host = $host;
	}
	
	public function setProxyPort($port) {
		$this->_proxy_port = intval($port);
	}
	
	public function setProxyUser($user) {
		$this->_proxy_name = $user;
	}
	
	public function setProxyPassword($pwd) {
		$this->_proxy_pass = $pwd;
	}
	
	public function setProxyType($type) {
		$this->_proxy_type = $type;
	}
	

	/**
	 * Construct cookie header for sending to remote site
	 */
	private function cookieHeader()
	{
		if (is_array($this->_cookies) && sizeof($this->_cookies)) {
			return 'Cookie: ' . implode('; ', $this->_cookies);
		}
		return '';
	}
}
