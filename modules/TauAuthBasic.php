<?php
/**
 * AuthBasic module.
 *
 * Heavily influenced by https://www.linuxquestions.org/questions/programming-9/php-how-to-validate-a-password-from-htpasswd-4175589072/#post5604195
 *
 * Sample usage:
 * $validated = TauAuthBasic::authorize('/var/www/.htpasswd');
 *
 * if (!$validated) {
 *   header('WWW-Authenticate: Basic realm="My Realm"');
 *   header('HTTP/1.0 401 Unauthorized');
 *	 die('Not authorized');
 * }
 *
 * echo 'Welcome ' . $_SERVER['PHP_AUTH_USER'] . "<br>\n";
 * echo 'Your password is ' . $_SERVER['PHP_AUTH_PW'];
 *
 * @Author theyak
 * @Copyright 2019
 *
 */


class TauAuthBasic {
	// APR1-MD5 encryption method (windows compatible)
	protected static function crypt_apr1_md5($plainpasswd, $salt) {
		$tmp = "";
		$len = strlen($plainpasswd);
		$text = $plainpasswd . '$apr1$' . $salt;
		$bin = pack("H32", md5($plainpasswd . $salt . $plainpasswd));
		for($i = $len; $i > 0; $i -= 16) {
			$text .= substr($bin, 0, min(16, $i));
		}
		for($i = $len; $i > 0; $i >>= 1) {
			$text .= ($i & 1) ? chr(0) : $plainpasswd{0};
		}
		$bin = pack("H32", md5($text));
		for($i = 0; $i < 1000; $i++) {
			$new = ($i & 1) ? $plainpasswd : $bin;
			if ($i % 3) {
				$new .= $salt;
			}
			if ($i % 7) {
				$new .= $plainpasswd;
			}
			$new .= ($i & 1) ? $bin : $plainpasswd;
			$bin = pack("H32", md5($new));
		}
		for ($i = 0; $i < 5; $i++) {
			$k = $i + 6;
			$j = $i + 12;
			if ($j == 16) {
				$j = 5;
			}
			$tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
		}
		$tmp = chr(0) . chr(0) . $bin[11] . $tmp;
		$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
		"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
		"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
		return '$apr1$' . $salt . '$' . $tmp;
	}


	/**
	 * Retrieves a user record from an .htpasswd file.
	 * Password must be in basic format, which is now
	 * the recommended format per Apache.
	 *
	 * @param  string $file Name of file to retrieve hashed password from. This is generally
	 *                named .htpasswd and should ordinarily be found outside the root
	 *                of your website.
	 * @param  string $username Username for auth
	 */
	public static function getHtpasswd($file, $username) {
		$lines = file($file);
		foreach ($lines as $line) {
			$arr = explode(':', $line);
			if (count($arr) === 2 && $username === $arr[0]) {
				return trim($arr[1]);
			}
		}
		return false;
	}


	/**
	 * Match the plain text password with the password found in the
	 * .htpassword file, or wherever you happen to get it from.
	 *
	 * @param string  $password The unencrypted password from the user
	 * @param string  $encrypted The encrypted password, usually from .htaccess,
	 *                but you could get it from anywhere, such as a database.
	 * @return bool true if match, false if no match
	 */
	public static function matchHtpasswd($password, $encrypted = null) {
		if (strpos($encrypted, '$apr1') === 0) {
			$parts = explode('$', $encrypted);
			if (count($parts) === 4) {
				$salt = $parts[2];
				$hashed = static::crypt_apr1_md5($password, $salt);
				return $hashed === $encrypted;
			}
			return false;
		} else if (strpos($encrypted, '{SHA}') === 0) {
			$hashed = "{SHA}" . base64_encode(sha1($password, true));
			return $hashed === $encrypted;
		} else if (strpos($encrypted, '$2y$') === 0) {
			return password_verify($password, $encrypted);
		} else {
			$salt = substr($encrypted, 0, 2);
			$hashed = crypt($password, $salt);
			return $hashed === $encrypted;
		}
	}


	/**
	 * Perform HTTP Basic Authentication from an .htpasswd file
	 *
	 * @param  string $file Name of file to retrieve hashed password from. This is generally
	 *                named .htpasswd and should ordinarily be found outside the root
	 *                of your website.
	 * @param  string $username Username for auth
	 * @param  string $password Plaintext password for auth
	 * @return bool true if match, false if no match
	 */
	public static function authorize($file, $username = false, $password = false) {
		if (!$username) {
			$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : false;
		}
		if (!$password) {
			$password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : false;
		}
		if (!$username || !$password) {
			return false;
		}
		$hashed = static::getHtpasswd($file, $username);
		if ($hashed) {
			$hashed = static::matchHtpasswd($password, $hashed);
		}
		return $hashed;
	}
}
