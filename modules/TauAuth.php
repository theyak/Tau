<?php
/**
 * Aith Module For TAU
 * Contains helper routines for authorizing users
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

class TauAuth
{
	/**
	 * Get IP address of user.
	 *
	 * @return string IP address of user
	 * 
	 * @source phpbb3, http://www.phpbb.com, includes/session.php:session_begin()
	 */
	public static function getUserIp()
	{
		$ips = (!empty($_SERVER['REMOTE_ADDR'])) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ips = preg_replace('# {2,}#', ' ', str_replace(',', ' ', $ips));

		// split the list of IPs
		$ips = explode(' ', trim($ips));

		// Default IP in case no valid IPs from REMOTE_ADDR
		$ip = '127.0.0.1';

		foreach ($ips as $ip)
		{
			if (TauValidate::is_ipv4($ip))
			{
				// Most likely case so check first, but do nothing
			}
			else if (TauValidate::is_ipv6($ip))
			{
				// Quick check for IPv4-mapped address in IPv6
				if (stripos($ip, '::ffff:') === 0)
				{
					$ipv4 = substr($ip, 7);

					if (preg_match(TauValidate::$ipv4, $ipv4))
					{
						$ip = $ipv4;
					}
				}
			}
			else
			{
				// We want to use the last valid address in the chain
				// Leave foreach loop when address is invalid
				break;
			}
		}

		return $ip;
	}
}
