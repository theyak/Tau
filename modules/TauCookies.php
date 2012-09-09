<?php
/**
 * Cookie module for TAU
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

class TauCookies
{
	public $prefix = '';
	public $domain = '';
	public $expires = 0;
	public $path = '/';
	public $secure = false;
	public $key = '';
	public $httpOnly = false;
	
	public function set($name, $value, $expires = null, $path = null, $domain = null, $secure = null, $httpOnly = false)
	{
		// Set default values
		$a = array('expires', 'path', 'domain', 'secure', 'httpOnly');
		foreach ($a AS $varName) {
			if (is_null($$varName)) {
				$$varName = $this->$varName;
			}
		}
		
		if (!empty($this->prefix)) {
			$name = $this->prefix . $name;
		}
		
		if (!empty($this->key)) {
			$encrypt = new TauEncryption();
			$value = $encrypt->encode($value, $this->key);
		}
		
		setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
		$_COOKIE[$name] = $value;
	}
	
	public function get($name)
	{
		if (!empty($this->prefix)) {
			$name = $this->prefix . $name;
		}
		
		if (isset($_COOKIE[$name])) 
		{
			if (!empty($this->key)) {
				$encrypt = new TauEncryption();
				$value = $encrypt->decode($_COOKIE[$name], $this->key);
				return $value;
			} else {
				return $_COOKIE[$name];
			}
		}
		return false;
	}
	
	public function exists($name)
	{
		if (!empty($this->prefix)) {
			$name = $this->prefix . $name;
		}

		return isset($_COOKIE[$name]);
	}
	
	public function is_set($name)
	{
		return $this->exists($name);
	}
	
	public function delete($name, $path = null, $domain = null, $secure = null, $httpOnly = false)
	{
		// Set default values
		$a = array('path', 'domain', 'secure', 'httpOnly');
		foreach ($a AS $varName) {
			if (is_null($$varName)) {
				$$varName = $this->$varName;
			}
		}

		if (!empty($this->prefix)) {
			$name = $this->prefix . $name;
		}
		setcookie($name, '', time() - 3600, $path, $domain, $secure, $httpOnly);
		unset($_COOKIE[$name]);
	}
	
	public function increment($name, $delta = 1)
	{
		$value = $this->get($name);
		$value += $delta;
		$this->set($name, $value);
	}
	
	public function decrement($name, $delta = 1)
	{
		$value = $this->get($name);
		$value -= $delta;
		$this->set($name, $value);		
	}
}
