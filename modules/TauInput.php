<?php

/**
 * Input Module For TAU.
 *
 * This one is still very much a work in progress.
 *
 * Why did I use filter_input instead of $_POST?!? Oh yeah, NetBeans
 * would complain about using $_POST. Of course, using filter_input()
 * means you can't do unit tests. Argh. Now I use VSCode which doesn't
 * complain, but I'm too afraid of messing something up by converting
 * to the super globals.
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 */
if (!defined('TAU')) {
	exit;
}

class TauInput
{

	/**
	 * Retrieve value from post request
	 *
	 * @param  string $field
	 * @param  int $sanitizer
	 * @return string|false False if field does not exist
	 */
	public static function post($field, $sanitizer = FILTER_UNSAFE_RAW)
	{
		$result = filter_input(INPUT_POST, $field, $sanitizer);
		if (is_null($result)) {
			return false;
		}

		return $result;
	}



	/**
	 * Retrieve value from get request
	 *
	 * @param  string $field
	 * @param  int $sanitizer
	 * @return string|false False if field does not exist
	 */
	public static function get($field, $sanitizer = FILTER_UNSAFE_RAW)
	{
		$result = filter_input(INPUT_GET, $field, $sanitizer);
		if (is_null($result)) {
			return false;
		}

		return $result;
	}



	/**
	 * Retrieve value from cookie
	 *
	 * @param  string $field
	 * @param  int $sanitizer
	 * @return string|false False if field does not exist
	 */
	public static function cookie($field, $sanitizer = FILTER_UNSAFE_RAW)
	{
		$result = filter_input(INPUT_COOKIE, $field, $sanitizer);
		if (is_null($result)) {
			return false;
		}

		return $result;
	}



	/**
	 * Retrieve value from the $_FILES request
	 *
	 * @param  string $field
	 * @return string|false False if field does not exist
	 */
	public static function files($field)
	{
		return (isset($_FILES[$field])) ? $_FILES[$field] : false;
	}



	/**
	 * Retrieve value from the $_SERVER varaible
	 *
	 * @param  string $field
	 * @return string|false False if field does not exist
	 */
	public static function server($field, $sanitizer = FILTER_UNSAFE_RAW)
	{
		$result = filter_input(INPUT_SERVER, $field, $sanitizer);
		if (is_null($result)) {
			return false;
		}

		return $result;
	}



	/**
	 * Retrieve value from post, get, or cookie. Post has priority
	 * over get, which has priority over cookie.
	 *
	 * @param  string $field
	 * @param  int $sanitizer
	 * @return string|false False if field does not exist
	 */
	public static function request($field, $sanitizer = FILTER_UNSAFE_RAW)
	{
		$result = static::post($field, $sanitizer);
		if ($result !== false) {
			return $result;
		}

		$result = static::get($field, $sanitizer);
		if ($result !== false) {
			return $result;
		}

		$result = static::cookie($field, $sanitizer);

		return $result;
	}



	// Exists
	// ---------------------------------------------------------------------------
	public static function exists($field, $post_only = false)
	{
		if ($post_only) {
			return static::post($field) !== false;
		}

		$result = static::request($field);
		return $result !== false;
	}



	// Integer
	// ---------------------------------------------------------------------------
	public static function int($fields, $default = 0, $post_only = false)
	{
		if (!is_array($fields)) {
			$fields = array($fields);
		}

		foreach ($fields as $field) {
			$result = self::post($field, FILTER_SANITIZE_NUMBER_INT);
			if ($result !== false) {
				return (int) $result;
			}

			if (!$post_only) {
				$result = self::get($field, FILTER_SANITIZE_NUMBER_INT);
				if ($result !== false) {
					return (int) $result;
				}

				$result = self::cookie($field, FILTER_SANITIZE_NUMBER_INT);
				if ($result !== false) {
					return (int) $result;
				}
			}
		}

		return (int) $default;
	}

	// String
	// ---------------------------------------------------------------------------
	public static function string($fields, $default = '', $post_only = false)
	{
		if (!is_array($fields)) {
			$fields = array($fields);
		}

		foreach ($fields as $field) {
			$result = self::post($field);

			if ($result !== false) {
				return (string) $result;
			}

			if (!$post_only) {
				$result = self::get($field);
				if ($result !== false) {
					return (string) $result;
				}

				$result = self::cookie($field);
				if ($result !== false) {
					return (string) $result;
				}
			}
		}

		return (string) $default;
	}

	// Boolean
	// ---------------------------------------------------------------------------
	static public function boolval($value)
	{
		if ((boolean) $value === false) {
			return false;
		}

		if (is_string($value)) {
			$value = strtolower($value);
			if (in_array($value, ['no', 'false', '0', 'off'])) {
				return false;
			}
		}

		return (boolean) $value;
	}



	public static function boolean($fields, $default = false, $post_only = false)
	{
		if (!is_array($fields)) {
			$fields = array($fields);
		}

		foreach ($fields as $field) {
			$result = self::post($field);
			if ($result !== false) {
				return static::boolval($result);
			}

			if (!$post_only) {
				$result = self::get($field);
				if ($result !== false) {
					return static::boolval($result);
				}

				$result = self::cookie($field);
				if ($result !== false) {
					return static::boolval($result);
				}
			}
		}

		return (boolean) $default;
	}



	// Array
	// ---------------------------------------------------------------------------
	public static function vector($field, $default = array(), $post_only = false)
	{
		$result = filter_input(INPUT_POST, $field, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		if ($result !== false && !is_null($result)) {
			return $result;
		}

		if (!$post_only) {
			$result = filter_input(INPUT_GET, $field, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

			if ($result !== false && !is_null($result)) {
				return $result;
			}

			$result = filter_input(INPUT_COOKIE, $field, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
			if ($result !== false && !is_null($result)) {
				return $result;
			}
		}

		return $default;
	}



	// Checkbox
	// ---------------------------------------------------------------------------
	public static function checkbox($field, $default = false, $post_only = false)
	{
		$result = self::post($field);
		if ($result !== false) {
			return $result === 'on';
		}

		if (!$post_only) {
			$result = self::get($field);
			if ($result !== false) {
				return $result === 'on';
			}
		}

		return $default;
	}



	// Email
	// ---------------------------------------------------------------------------
	public static function email($field, $default = false, $post_only = false)
	{
		$result = self::post($field, FILTER_SANITIZE_EMAIL);
		if ($result !== false) {
			return $result;
		}

		if (!$post_only) {
			$result = self::get($field, FILTER_SANITIZE_EMAIL);
			if ($result !== false) {
				return $result;
			}
		}

		return $default;
	}



	public static function xssString($fields, $default = '', $post_only = false)
	{
		return htmlentities(self::string($fields, $default, $post_only));
	}
}