<?php
/**
 * Cookie module for TAU
 *
 * @Author          theyak
 * @Copyright       2013
 * @Project Page    None!
 * @docs            None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauCookie
{
	/**
	 * Set a cookie. Works a lot like PHP's set_cookie() except the path
	 * is automatically set to the root folder and $httpOnly is set to true,
	 * which is important for overly zealous PCI checkers. If you do not
	 * know what PCI is, consider yourself lucky.
	 *
	 * @param {string|assoc} $name
	 * @param {mixed}   $value
	 * @param {int}     $expires
	 * @param {string}  $path
	 * @param {string}  $domain
	 * @param {boolean} $secure
	 * @param {boolean} $httpOnly
	 */
	public static function set(
		$name,
		$value = '',
		$expires = 0,
		$path = '/',
		$domain = '',
		$secure = false,
		$httpOnly = true
	)
	{
		if (is_array($name))
		{
			foreach ($name AS $key => $val)
			{
				${$key} = $val;
			}
		}

		// If an array of parameters was passed, one may be an encryption.
		if (isset($encryption) && !empty($encryption)) {
			$encrypt = new TauEncryption();
			$value = $encrypt->encode($value, $encryption);
		}

		setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
		$_COOKIE[$name] = $value;
	}



	/**
	 * Set a cookie. Works a lot like PHP's set_cookie() except the path
	 * is automatically set to the root folder and $httpOnly is set to true.
	 *
	 * @param {string|assoc} $name
	 * @param {string}  $encryption
	 * @param {mixed}   $value
	 * @param {int}     $expires
	 * @param {string}  $path
	 * @param {string}  $domain
	 * @param {boolean} $secure
	 * @param {boolean} $httpOnly
	 */
	public static function setEncrypted(
		$name,
		$encryption,
		$value = '',
		$expires = 0,
		$path = '/',
		$domain = '',
		$secure = false,
		$httpOnly = true
	)
	{
		if (is_array($name))
		{
			foreach ($name AS $key => $val)
			{
				$$key = $val;
			}
		}

		if (!empty($encryption)) {
			$encrypt = new TauEncryption();
			$value = $encrypt->encode($value, $encryption);
		}


		setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
		$_COOKIE[$name] = $value;
	}



	/**
	 * Retrieve a cookie
	 *
	 * @param {string} $name Name of cookie to retrieve
	 * @param {mixed}  $default Default value to return if cookie doesn't exist
	 * @return {mixed} Value of cookie or, if it doesn't exist, the value of $default
	 */
	public static function get($name, $default = false)
	{
		if (isset($_COOKIE[$name]))
		{
			return $_COOKIE[$name];
		}
		return $default;
	}



	/**
	 * Retrieve an encrypted cookie
	 *
	 * @param {string} $name Name of cookie to retrieve
	 * @param {string} $encryption Encryption key
	 * @param {mixed}  $default Default value to return if cookie doesn't exist
	 * @return {mixed} Value of cookie or, if it doesn't exist, the value of $default
	 */
	public static function getEncrypted($name, $encryption, $default = false)
	{
		if (isset($_COOKIE[$name]))
		{
			if (!empty($encryption)) {
				$encrypt = new TauEncryption();
				return $encrypt->decode($_COOKIE[$name], $encryption);
			}

			return $_COOKIE[$name];
		}
		return $default;
	}



	/**
	 * Checks if a cookie exists
	 *
	 * @param {string} $name Name of cookie to check
	 * @return {boolean}
	 */
	public static function exists($name)
	{
		return isset($_COOKIE[$name]);
	}



	/**
	 * Checks if a cookie exists
	 *
	 * @param {string} $name Name of cookie to check
	 * @return {boolean}
	 */
	public static function is_set($name)
	{
		return $this->exists($name);
	}



	/**
	 * Removes a cookie. In order for a cookie to be properly removed, all the
	 * parameters, except the lacking $value, must be the same as those set
	 * with the cookie.
	 *
	 * @param {string|assoc} $name
	 * @param {int}     $expires
	 * @param {string}  $path
	 * @param {string}  $domain
	 * @param {boolean} $secure
	 * @param {boolean} $httpOnly
	 */
	public static function delete(
		$name,
		$expires = 0,
		$path = '/',
		$domain = '',
		$secure = false,
		$httpOnly = true
	)
	{
		if (is_array($name))
		{
			foreach ($name AS $key => $val)
			{
				$$key = $val;
			}
		}

		setcookie($name, '', 1, $path, $domain, $secure, $httpOnly);
		unset($_COOKIE[$name]);
	}



	/**
	 * Increase the value of a cookie if it is numeric.
	 *
	 * @param {string} $name
	 * @param {number} $delta
	 */
	public static function increment($name, $delta = 1)
	{
		$value = static::get($name);
		if (is_numeric($value)) {
			$value += $delta;
			$this->set($name, $value);
		}
	}



	/**
	 * Decrease the value of a cookie if it is numeric.
	 *
	 * @param {string} $name
	 * @param {number} $delta
	 */
	public static function decrement($name, $delta = 1)
	{
		$value = static::get($name);
		if (is_numeric($value)) {
			$value -= $delta;
			$this->set($name, $value);
		}
	}
}
