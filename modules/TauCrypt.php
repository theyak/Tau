<?php
/**
 * Encryption module for TAU
 *
 * @Author          theyak
 * @Copyright       2018
 * @Project Page    https://github.com/theyak/Tau
 * @docs            None!
 *
 * 2018-07-24 Created, based on TauEncryption with updated cipers and such
 */

/*
Examples:

// Static calls
$encoded = TauCrypt::encrypt( 'bob', 'hi' );
$decoded = TauCrypt::decrypt( $encoded, 'hi' );
Tau::dump($encoded);
Tau::dump($decoded);
*/

class TauCrypt
{
    /**
     * Default key
     * @var string
     */
    public $key = '';

    public static $cipher = "AES-256-CBC";

    public static function getRandomKey($length = 32)
    {
        return openssl_random_pseudo_bytes($length);
    }

    public function __construct($key = null)
    {
        if (! is_null($key)) {
            $this->key = $key;
        }
    }

    public function encode($plainText, $key = null)
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return static::encrypt($plainText, $key);
    }

    public function decode($encryptedString, $key = null)
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return static::decrypt($encryptedString, $key);
    }

    public static function encrypt($plainText, $key)
    {
        $ivlen = openssl_cipher_iv_length(static::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $raw = openssl_encrypt($plainText, static::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac("sha256", $raw, $key, true);
        return base64_encode($iv . $hmac . $raw);
    }

    public static function decrypt($encryptedString, $key)
    {
        $c = base64_decode($encryptedString);
        $ivlen = openssl_cipher_iv_length(static::$cipher);
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, 32);
        $raw = substr($c, $ivlen + 32);
        $plainText = openssl_decrypt($raw, static::$cipher, $key, OPENSSL_RAW_DATA, $iv);

        // Added in PHP 5.6
        if (function_exists("hash_hmac") && function_exists("hash_equals")) {
            $calcmac = hash_hmac("sha256", $raw, $key, true);
            if (hash_equals($hmac, $calcmac)) {
                return $plainText;
            }
            return false;
        }

        return $plainText;
    }
}
