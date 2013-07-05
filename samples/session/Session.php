<?php

namespace TauSessionSample;

class Session
{
	/**
	 *
	 * @var \TauDb 
	 */
    public static $db;
	
	public static $session_time = 14400;
    
    public $loaded = false;

    // Session Schema
    public $session_id;
	public $user_id;
    public $expires;
    public $ip;
    public $browser;
    public $session_data;
	
	public $username;
	public $logged_in = false;
	public $crawler = false;
    

    function __construct()
    {
		$this->expires = time() + static::$session_time;
		$this->ip = \TauAuth::getUserIp();
		$this->crawler = \TauAuth::isCrawler();
		$this->browser = $_SERVER['HTTP_USER_AGENT'];
		$this->page = $_SERVER['REQUEST_URI'];
		
        $this->get();
    }
    
    public function get()
    {	
        $session_id = \TauCookie::get('sessid');
		$user_id = intval(\TauCookie::get('uid'));
		
		// If the user does not have a session id, then they 
		// are not logged in and they need a new session made.
		if (!$session_id) {
			$this->create();
			return;
		}

		$sql = "SELECT * 
			FROM sessions 
			WHERE session_id = " . static::$db->stringify($session_id) . "
				AND user_id = " . $user_id;
		$row = static::$db->fetchOne($sql);
		
		// Could not find session, or it expired, create a new one
		if (!$row) {
			$this->create();
			return;
		}
		
		if ($row['session_expires'] < time()) {
			static::delete($session_id);
			$this->create();
			return;
		}
		
		// TODO: Occasionally change the session id to prevent session hijacking
		
		// Everything looks good
		$this->session_id = $session_id;
		$this->user_id = $user_id;
		$this->session_data = json_decode($row['session_data'], true);

		$this->loaded = true;
		
		$user = User::get($user_id);

		$this->username = $user->username;
		$this->logged_in = $user->user_id > 1;
		
		$this->save();
    }
    
    public function create()
    {
		$user = User::get(1);
		
		$this->session_id = $this->generateId();
		$this->user_id = 1;
		$this->session_data = json_decode('{}', true);
		$this->loaded = false;
		
		$this->save();
        
		$this->username = $user->username;
		$this->logged_in = false;
    }
	
	public function destroy()
	{
		\TauCookie::delete('sessid');
		\TauCookie::delete('uid');
		static::delete($this->session_id);
	}
	
	public function login($user)
	{
		$old_user_id = $this->user_id;
		$this->username = $user->username;
		$this->user_id = $user->user_id;
		$this->logged_in = true;
		$this->save($old_user_id);
	}
	
	public function save($user_id = false)
	{
		if (!$user_id) {
			$user_id = $this->user_id;
		}
		
		$array = array(
			'session_id' => $this->session_id,
			'user_id' => $this->user_id,
			'session_expires' => $this->expires,
			'session_ip' => $this->ip,
			'session_browser' => substr($this->browser, 0, 160),
			'session_data' => json_encode($this->session_data),
		);
		if ($this->loaded) {
			static::$db->update('sessions', $array, array(
				'session_id' => $this->session_id,
				'user_id' => $user_id
			));
		} else {
			static::$db->insert('sessions', $array);
			$this->loaded = true;
		}
		\TauCookie::set('sessid', $this->session_id);
		\TauCookie::set('uid', $this->user_id);
	}
	
	public function generateId()
	{
		return md5(uniqid() . uniqid());
	}
    
    public static function setDb($db)
    {
        static::$db = $db;
    }
	
	public static function delete($session_id)
	{
		$sql = "DELETE FROM sessions WHERE session_id = " . static::$db->stringify($session_id);
		static::$db->query($sql);
	}
	
	public static function purge()
	{
		$sql = "DELETE FROM sessions WHERE expires < " . time();
		static::$db->query($sql);
	}
}