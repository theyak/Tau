<?php
/**
 * Crypt module for what may become Tau2 or something else entirely.
 * This is based on Tau's TauEncryption class.
 *
 * @Author          theyak
 * @Copyright       2018
 * @Project Page    https://github.com/theyak/Tau
 * @docs            None!
 *
 * 2018-07-24 Created
 */

/*
Example:

include "Tau/modules/Crypt.php";

use \Theyak\Tau\Crypt;

$key = Crypt::getRandomKey();
$encoded = Crypt::encrypt( $key, 'This is plain text' );
$decoded = Crypt::decrypt( $key, $encoded );

echo $key . "\n";
echo $encoded . "\n";
echo $decoded . "\n";
*/

namespace Theyak\Tau;

class Crypt
{
    public static $cipher = "AES-256-CBC";

    public static function getRandomKey($length = 32)
    {
        return openssl_random_pseudo_bytes($length);
    }

    public static function encrypt($key, $plainText)
    {
        $ivlen = openssl_cipher_iv_length(static::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $raw = openssl_encrypt($plainText, static::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac("sha256", $raw, $key, true);
        return base64_encode($iv . $hmac . $raw);
    }

    public static function decrypt($key, $encryptedString)
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
