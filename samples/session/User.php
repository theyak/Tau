<?php

namespace TauSessionSample;

class User
{
	/**
	 * @var \TauDb
	 */
    public static $db = null;

	public $max_login_attempts = 5;
	
    public $loaded = false;
	public $dirty = false;
   
    // User Schema
    public $user_id;
	public $activated;
    public $username;
    public $username_clean;
    public $email;
    public $password_hash;
    public $login_attempts;
    public $created_at;
    public $last_login;
    public $code;
    public $user_data;

	private static $usersById = array();
	private static $usersByName = array();
	
	public static function get($user = 0)
	{
		$user_id = intval($user);
		if ($user_id > 0) {
			if (isset(static::$usersById[$user_id])) {
				return static::$usersById[$user_id];
			}
			$user = new User();
			$user->getById($user_id);
		} else if ($user) {
			$username_clean = mb_strtolower($user);
			if (isset(static::$usersByName[$username_clean])) {
				return static::$usersByName[$username_clean];
			}
			$user = new User();
			$this->getByLogin($username_clean);
		}
		if (!$user->loaded) {
			return false;
		}
		return $user;
	}
	
    public function __construct($user = 0)
    {
    }

    public function getById($user_id)
    {
		$sql = "SELECT * FROM users WHERE user_id = " . intval($user_id);
		$row = static::$db->fetchOne($sql);
		
		if ($row) {
			$this->fromArray($row);
		}
	}
    
    public function getByUsername($username)
    {
		$sql = "SELECT * FROM users WHERE username_clean = " . static::$db->stringify(mb_strtolower($username));
		$row = static::$db->fetchOne($sql);
		
		if ($row) {
			$this->fromArray($row);
		}
    }
    
    public function getByEmail($email)
    {
		$sql = "SELECT * FROM users WHERE email = " . static::$db->stringify(mb_strtolower($email));
		$row = static::$db->fetchOne($sql);
		
		if ($row) {
			$this->fromArray($row);
		}        
    }
    
    public function getByLogin($login)
    {
        $this->getByUsername($login);
		if (!$this->loaded) {
			$this->getByEmail($login);
		}
    }
	
	public function getByAny($login)
	{
        $this->getByUsername($login);
		if (!$this->loaded && intval($login) > 0) {
			$this->getById($login);
		}
		if (!$this->loaded) {
			$this->getByEmail($login);
		}
		
	}
    
	public function fromArray($array)
	{
		$fields = array(
			'username',
			'username_clean',
			'email',
			'password',
			'created_at',
			'last_login',
			'code',
			'user_data'
		);
		foreach ($fields AS $field) {
			if (isset($array[$field])) {
				$this->$field = $array[$field];
			}
		}
		$this->user_data = json_decode($array['user_data'], true);
		$this->user_id = intval($array['user_id']);
		$this->login_attempts = intval($array['login_attempts']);
		$this->activated = (boolean)$array['activated'];
		
		static::$usersByName[$array['username_clean']] = $this;
		static::$usersById[$array['user_id']] = $this;
		
		$this->loaded = true;
	}
	
    public function setCode()
    {
        $this->code = md5(uniqid() . uniqid());
    }
    
    public function setPassword($password)
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);        
        if (password_verify($password, $this->password_hash)) {
            $this->password = $hash;
			$this->dirty = true;
        } else {
            /* Invalid */
        }
    }
    
    public function setUsername($username)
    {
        $this->username = $username;
		$this->username_clean = mb_strtolower($username);
		$this->dirty = true;
    }
    
    public function setEmail($email)
    {
        $this->email = mb_strtolower($email);
		$this->dirty = true;
    }
	
	public function setActive($active)
	{
		$this->activated = $active;
		$this->dirty= true;
	}
	
    public function verify($password)
    {
        if (password_verify($password, $this->password) || $password == $this->password) {
            return true;
        } else {
            return false;
        }        
    }
	
	public function save() 
	{
		$array = array(
			'activated' => $this->activated,
			'username' => $this->username,
			'username_clean' => mb_strtolower($this->username),
			'email' => $this->email,
			'password' => $this->password,
			'login_attempts' => $this->login_attempts,
			'last_login' => $this->last_login,
			'code' => $this->code,
			'user_data' => json_encode($this->user_data)
		);
		if ($this->loaded) {
			static::$db->update('users', $array, array('user_id' => $this->user_id));
		} else {
			$array['created_at'] = date('Y-m-d H:i:s');
			static::$db->insert('users', $array);
			$this->user_id = static::$db->insertId();
		}
		$this->loaded = true;
		$this->dirty = false;
	}
	    
    public static function isUsernameAvailable($username)
    {
        $username = mb_strtolower($username);
		
		$row = static::$db->fetchOne("SELECT username FROM users WHERE username_clean = " . $db->stringify($username));
		return !is_array($row);
    }
    
    public static function isEmailAvailable($email)
    {
        $email = mb_strtolower($email);
		
		$row = static::$db->fetchOne("SELECT username FROM users WHERE email = " . $db->stringify($email));
		return !is_array($row);        
    }
	
    public static function login($login, $password, $username = true, $email = true)
    {
		global $session;
		
        $user = new User;
        if ($username && $email) {
            $user->getByLogin($login);
        } else if ($username) {
            $user->getByUsername($login);
        } else if ($email) {
            $user->getByEmail($login);            
        }
		if (!$user->loaded) {
			return 'NO_USER';
		}
		if ($user->login_attempts >= $this->max_login_attempts) {
			return 'TOO_MANY_ATTEMPTS';
		}
		
        if ($user->verify($password)) {
			$session->login($user);
        } else {
			return 'INVALID_PASSWORD';
		}
		return $user;
    }
    
    public static function createUser($username, $email, $password, $activated = false)
    {
        $user = new User();
		$user->setUsername($username);
		$user->setEmail($email);
		$user->setPassword($password);
		$user->setActive($activated);
		$user->last_login = "0000-00-00 00:00:00";
		$user->save();
    }
    
    public static function activateUser($user_id, $activation_key)
    {
		if ($activation_key === $this->code) {
			$this->code = '';
			$this->activated = true;
			$this->dirty = true;
		}
    }
    
    public static function purgeInactiveUsers($expirePeriod = 60)
    {
		$expirePeriod = intval($expirePeriod);
		
        $sql = "DELETE FROM users 
			WHERE created_at < DATE_SUB(NOW(), INTERVAL " . $expirePeriod . " DAY) 
				AND (activated = 0 OR last_login = '0000-00-00 00:00:00')";
		static::$db->query($sql);
    }

    public static function deleteUser($user_id)
    {
        static::$db->query('DELETE FROM users WHERE user_id = ' . intval($user_id));
    }
        
    public static function setDb($db)
    {
        static::$db = $db;
    }
	
	public function __destruct()
	{
		if ($this->dirty) {
			$this->save();
		}
	}
}


/**
 * A Compatibility library with PHP 5.5's simplified password hashing API.
 *
 * @author Anthony Ferrara <ircmaxell@php.net>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @copyright 2012 The Authors
 * 
 * @see https://raw.github.com/ircmaxell/password_compat/master/lib/password.php
 */

if (!defined('PASSWORD_DEFAULT')) {

    define('PASSWORD_BCRYPT', 1);
    define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);

    /**
     * Hash the password using the specified algorithm
     *
     * @param string $password The password to hash
     * @param int    $algo     The algorithm to use (Defined by PASSWORD_* constants)
     * @param array  $options  The options for the algorithm to use
     *
     * @return string|false The hashed password, or false on error.
     */
    function password_hash($password, $algo, array $options = array()) {
        if (!function_exists('crypt')) {
            trigger_error("Crypt must be loaded for password_hash to function", E_USER_WARNING);
            return null;
        }
        if (!is_string($password)) {
            trigger_error("password_hash(): Password must be a string", E_USER_WARNING);
            return null;
        }
        if (!is_int($algo)) {
            trigger_error("password_hash() expects parameter 2 to be long, " . gettype($algo) . " given", E_USER_WARNING);
            return null;
        }
        switch ($algo) {
            case PASSWORD_BCRYPT:
                // Note that this is a C constant, but not exposed to PHP, so we don't define it here.
                $cost = 10;
                if (isset($options['cost'])) {
                    $cost = $options['cost'];
                    if ($cost < 4 || $cost > 31) {
                        trigger_error(sprintf("password_hash(): Invalid bcrypt cost parameter specified: %d", $cost), E_USER_WARNING);
                        return null;
                    }
                }
                // The length of salt to generate
                $raw_salt_len = 16;
                // The length required in the final serialization
                $required_salt_len = 22;
                $hash_format = sprintf("$2y$%02d$", $cost);
                break;
            default:
                trigger_error(sprintf("password_hash(): Unknown password hashing algorithm: %s", $algo), E_USER_WARNING);
                return null;
        }
        if (isset($options['salt'])) {
            switch (gettype($options['salt'])) {
                case 'NULL':
                case 'boolean':
                case 'integer':
                case 'double':
                case 'string':
                    $salt = (string) $options['salt'];
                    break;
                case 'object':
                    if (method_exists($options['salt'], '__tostring')) {
                        $salt = (string) $options['salt'];
                        break;
                    }
                case 'array':
                case 'resource':
                default:
                    trigger_error('password_hash(): Non-string salt parameter supplied', E_USER_WARNING);
                    return null;
            }
            if (strlen($salt) < $required_salt_len) {
                trigger_error(sprintf("password_hash(): Provided salt is too short: %d expecting %d", strlen($salt), $required_salt_len), E_USER_WARNING);
                return null;
            } elseif (0 == preg_match('#^[a-zA-Z0-9./]+$#D', $salt)) {
                $salt = str_replace('+', '.', base64_encode($salt));
            }
        } else {
            $buffer = '';
            $buffer_valid = false;
            if (function_exists('mcrypt_create_iv') && !defined('PHALANGER')) {
                $buffer = mcrypt_create_iv($raw_salt_len, MCRYPT_DEV_URANDOM);
                if ($buffer) {
                    $buffer_valid = true;
                }
            }
            if (!$buffer_valid && function_exists('openssl_random_pseudo_bytes')) {
                $buffer = openssl_random_pseudo_bytes($raw_salt_len);
                if ($buffer) {
                    $buffer_valid = true;
                }
            }
            if (!$buffer_valid && is_readable('/dev/urandom')) {
                $f = fopen('/dev/urandom', 'r');
                $read = strlen($buffer);
                while ($read < $raw_salt_len) {
                    $buffer .= fread($f, $raw_salt_len - $read);
                    $read = strlen($buffer);
                }
                fclose($f);
                if ($read >= $raw_salt_len) {
                    $buffer_valid = true;
                }
            }
            if (!$buffer_valid || strlen($buffer) < $raw_salt_len) {
                $bl = strlen($buffer);
                for ($i = 0; $i < $raw_salt_len; $i++) {
                    if ($i < $bl) {
                        $buffer[$i] = $buffer[$i] ^ chr(mt_rand(0, 255));
                    } else {
                        $buffer .= chr(mt_rand(0, 255));
                    }
                }
            }
            $salt = str_replace('+', '.', base64_encode($buffer));
        }
        $salt = substr($salt, 0, $required_salt_len);

        $hash = $hash_format . $salt;

        $ret = crypt($password, $hash);

        if (!is_string($ret) || strlen($ret) <= 13) {
            return false;
        }

        return $ret;
    }

    /**
     * Get information about the password hash. Returns an array of the information
     * that was used to generate the password hash.
     *
     * array(
     *    'algo' => 1,
     *    'algoName' => 'bcrypt',
     *    'options' => array(
     *        'cost' => 10,
     *    ),
     * )
     *
     * @param string $hash The password hash to extract info from
     *
     * @return array The array of information about the hash.
     */
    function password_get_info($hash) {
        $return = array(
            'algo' => 0,
            'algoName' => 'unknown',
            'options' => array(),
        );
        if (substr($hash, 0, 4) == '$2y$' && strlen($hash) == 60) {
            $return['algo'] = PASSWORD_BCRYPT;
            $return['algoName'] = 'bcrypt';
            list($cost) = sscanf($hash, "$2y$%d$");
            $return['options']['cost'] = $cost;
        }
        return $return;
    }

    /**
     * Determine if the password hash needs to be rehashed according to the options provided
     *
     * If the answer is true, after validating the password using password_verify, rehash it.
     *
     * @param string $hash    The hash to test
     * @param int    $algo    The algorithm used for new password hashes
     * @param array  $options The options array passed to password_hash
     *
     * @return boolean True if the password needs to be rehashed.
     */
    function password_needs_rehash($hash, $algo, array $options = array()) {
        $info = password_get_info($hash);
        if ($info['algo'] != $algo) {
            return true;
        }
        switch ($algo) {
            case PASSWORD_BCRYPT:
                $cost = isset($options['cost']) ? $options['cost'] : 10;
                if ($cost != $info['options']['cost']) {
                    return true;
                }
                break;
        }
        return false;
    }

    /**
     * Verify a password against a hash using a timing attack resistant approach
     *
     * @param string $password The password to verify
     * @param string $hash     The hash to verify against
     *
     * @return boolean If the password matches the hash
     */
    function password_verify($password, $hash) {
        if (!function_exists('crypt')) {
            trigger_error("Crypt must be loaded for password_verify to function", E_USER_WARNING);
            return false;
        }
        $ret = crypt($password, $hash);
        if (!is_string($ret) || strlen($ret) != strlen($hash) || strlen($ret) <= 13) {
            return false;
        }

        $status = 0;
        for ($i = 0; $i < strlen($ret); $i++) {
            $status |= (ord($ret[$i]) ^ ord($hash[$i]));
        }

        return $status === 0;
    }
}



