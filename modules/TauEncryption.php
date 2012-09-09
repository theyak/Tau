<?php
/**
 * Encryption module for TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Based on        http://stackoverflow.com/questions/1289061/best-way-to-use-php-to-encrypt-and-decrypt
 * @Project Page    None!
 * @docs            None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauEncryption
{
	/**
	 * Default key
	 * @var string
	 */
	public $key = '';
	
    private function safe_b64encode($string)
	{
        $data = base64_encode($string);
        $data = str_replace(array('+','/','='), array('-','_',''), $data);
        return $data;
    }

    private function safe_b64decode($string)
	{
        $data = str_replace(array('-','_'), array('+','/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public function encode($value, $key = '')
	{ 
        if (!$value) {
			return false;
		}
		
		if (empty($key)) {
			$key = $this->key;
		}

        $text = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB, $iv);
		
        return trim($this->safe_b64encode($crypttext)); 
    }

    public function decode($value, $key = '')
	{
        if (!$value) {
			return false;
		}
		
		if (empty($key)) {
			$key = $this->key;
		}
		
        $crypttext = $this->safe_b64decode($value); 
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $crypttext, MCRYPT_MODE_ECB, $iv);
		
        return trim($decrypttext);
    }
}
