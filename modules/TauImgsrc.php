<?php
/**
 * Imgsrc.ru module for TAU
 *
 * @Author          levans
 * @Copyright       2011
 * @Depends on      TauHttp, curl
 * @Project Page    None!
 * @docs            None!
 *
 */


/**
 * Example Usage:
$http = new TauHttp();
$http->setProxyHost('127.0.0.1');
$http->setProxyPort(8118);
Tau::dump(TauImgsrc::getImageUrlByNumber(24225230, '12345', $http));
 */



class TauImgsrc
{
	
	/**
	 * Get image URL for an image number. This is achieved by navigating to a link with
	 * a fake user name and the number. The page contains the actual image URL embeded
	 * in phpBB [img] tags. That URL is returned. TauHttp, along with curl, is required
	 * for this to work.  
	 * 
	 * This method will cease functioning if imgsrc.ru changes their format.
	 * 
	 * @param integer $number   Image number to obtain
	 * @param string  $password Password for album in which image is contained
	 * @param TauHttp $http     A reference to a TauHttp instance. Useful if you want to
	 *                          customize your connection
	 * @return string           URL of image. 
	 */
	public static function getImageUrlByNumber($number, $password = null, $http = null)
	{
		$pwd = '';
		
		$url = 'http://imgsrc.ru/google/' . $number . '.html';

		if (is_null($http) or !($http instanceof TauHttp))
		{
			$http = new TauHttp();
			$http->setUserAgent('Google');
			echo 'oops';
			exit;
		}
		
		$result = $http->fetch($url);
		
		// Check if password protected and try to find password
		$regex = '@form\sname\=passchk\saction\=(.*)\smethod@';
		$matches = array();
		preg_match($regex, $result, $matches);
		if (isset($matches[1]))
		{
			$passwords = array();
			if (!is_null($password)) {
				$passwords[] = $password;
			}
			$passwords[] = '12345';
			$passwords[] = '123454321';
			$passwords[] = '54321';
			
			foreach ($passwords AS $password)
			{
				$http->setPostField('pwd', $password);
				$result = $http->fetch($url);
				if (strpos($result, 'Copy forum code') !== false) {
					// Find hashed password which resets every hour :(
					$regex = '@pwd=(.*)\#bp@';
					$matches = array();
					preg_match($regex, $result, $matches);
					$pwd = $matches[1];
					break;
				}
			}
			if (empty($pwd))
			{
				return false;
			}
		}
		
		// Find exact URL for image
		$regex = '@\[IMG\](.*)\[\/IMG\]@';
		$matches = array();
		preg_match($regex, $result, $matches);
		if (isset($matches[1])) {
			//if ($pwd) {
				//return $matches[1] . '?pwd=' . $pwd;
			//}
			return $matches[1];
		}
		
		return false;
	}
	
	public static function getAlbum($number, $password = null)
	{
		
	}
}
