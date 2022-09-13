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
	private $_cookies = [];
	private $_agent = 'Tau';
	private $_post = [];
    private $_headers = [];
	
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
    public $error_number = 0;
	public $error = '';

	
	function __construct($url = null) 
	{
		if (!is_null($url)) {
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



	/**
	 * Gets the headers of a URL. This is similar to the PHP function except:
	 *  * It does not make an array of redirects
	 *  * It works in PHP < 5
	 *  * The array is associative given the header name or http_code
	 *  * It's about twice as fast
	 * @param string $url URL to retrieve
	 * @return string[]
	 */
	public static function get_headers( $url )
	{
		$headers = array();
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'HEAD' );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_URL, $url );
		$data = curl_exec( $ch );
		
		if ( $data !== false ) {
			$lines = explode( "\n", $data );
			foreach ( $lines AS $line ) {
				$parts = explode( ':', $line, 2 );
				if ( sizeof( $parts ) === 2 ) {
					$headers[ trim( $parts[ 0 ] ) ] = trim( $parts[ 1 ] ); 
				} else if ( substr( $line, 0, 4 ) === 'HTTP' ) {
					$headers[ 'http_code' ] = substr( $line, 9, 3 );
				}
			}
		} else {
			// Should this be 404 or something else?
			$headers[ 'http_code' ] = 404;
		}
		
		return $headers;
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
		if (sizeof($this->_post)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_post);
		}
		
		if (!empty($this->_agent)) {
			curl_setopt($ch, CURLOPT_USERAGENT,	$this->_agent);
		}

		if (!empty($this->_proxy_host)) {
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

		if ($this->_headers) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_headers);
		}
        
		$result = curl_exec($ch);

		$this->info = curl_getinfo($ch);
		$this->responseCode = $this->info['http_code'];
		$this->error_number = curl_errno($ch);
		$this->error = curl_error($ch);
        
        curl_close($ch);

		$this->responseHeaders = array();
		while (substr($result, 0, 4) == 'HTTP') {
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
			'Firefox' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:101.0) Gecko/20100101 Firefox/101.0',
			'IE8' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0)',
			'Google' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/102.0.5005.115 Safari/537.36',
			'Edge' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 Edge/16.16299"',
        );
		
		if (isset($agents[$agent]))	{
			$this->_agent = $agents[$agent];
		} else {
			$this->_agent = $agent;
		}
	}
	
	public function setTimeout($timeout = 5)
	{
		$this->_timeout = intval($timeout);
	}
	
	public function setCookie($key, $value = null)
	{
		if (is_array($key)) {
			foreach ($key AS $name => $value) {
				$this->_cookies[] = $name . '=' . $value;
			}
		} else {
			$this->_cookies[] = $key . '=' . $value;
		}
	}
	
	
	public function setPostField($key, $value = null)
	{
		if (is_array($key)) {
			$this->_post = array_merge($this->_post, $key);
		} else {
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

	public function addHeader($key, $value) {
		$this->_headers[] = $key . ': ' . $value;
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
