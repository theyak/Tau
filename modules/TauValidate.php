<?php
/**
 * Validate Module For TAU
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

class TauValidate
{
	public static $ipv4 = '#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#';
	public static $ipv6 = '#^(?:(?:(?:[\dA-F]{1,4}:){6}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:::(?:[\dA-F]{1,4}:){0,5}(?:[\dA-F]{1,4}(?::[\dA-F]{1,4})?|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:):(?:[\dA-F]{1,4}:){4}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,2}:(?:[\dA-F]{1,4}:){3}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,3}:(?:[\dA-F]{1,4}:){2}(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,4}:(?:[\dA-F]{1,4}:)(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,5}:(?:[\dA-F]{1,4}:[\dA-F]{1,4}|(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])))|(?:(?:[\dA-F]{1,4}:){1,6}:[\dA-F]{1,4})|(?:(?:[\dA-F]{1,4}:){1,7}:)|(?:::))$#i';

	public static function is_ipv4($data)
	{
		if ( is_object( $data ) ) return false;
		if ( is_array( $data ) ) return false;

		return preg_match(TauValidate::$ipv4, $data);
	}

	public static function is_ipv6($data)
	{
		if ( is_object( $data ) ) return false;
		if ( is_array( $data ) ) return false;

		return preg_match(TauValidate::$ipv6, $data);
	}


	/**
	 * Check if email is valid.
	 *
	 * @param string $email Email address
	 * @param bool $strict No longer used. Kept for compatability.
	 * @return bool
	 */
    public static function is_email($data, $strict = false)
	{
		if (!is_string($email)) {
			return false;
		}

		$match = preg_match("/^[A-Z0-9._%+\-]+@([A-Z0-9\-]+\.)+[A-Z]{2,}$/i", $email);
		if (!$match) {
			return false;
		}

		// The following checks could be done as part of the regex, but then the
		// regex gets really complilcated and unmaintainable, so we use regular
		// code logic to do some extra checks.

		$parts = explode('@', $email);
		$localpart = $parts[0];
		$domain = $parts[1];

		// Check that local part does not start or end in a period
		if (trim($localpart, ".") !== $localpart) {
			return false;
		}

		// Check that domain starts with a letter or number
		if (!ctype_alnum(substr($domain, 0, 1))) {
			return false;
		}

		// Check that local part is not too long
		if (strlen($localpart) > 64) {
			return false;
		}

		return true;
	}
	
	public static function is_integer($value, $positiveOnly = false)
	{
		if ( is_object( $value ) ) return false;
		if ( is_array( $value ) ) return false;
		
		if ($positiveOnly) {
			return preg_match('/^\d+$/', $value) == 1;		
		}
		return preg_match('/^[-]?\d*$/', $value) == 1;
	}
	
	public static function is_integer_in_range($value, $min, $max)
	{
		if ( is_object( $value ) ) return false;
		if ( is_array( $value ) ) return false;
		
		if (!is_numeric($min) || !is_numeric($max)) {
			return false;
		}
		
		if (!self::is_integer($value)) {
			return false;
		}
		
		if ($min > $max) {
			$temp = $min;
			$min = $max;
			$max = $temp;
		}
		
		if ($value >= $min && $value <= $max) {
			return true;
		}
		return false;
	}
	
	public static function is_length($value, $min = 0, $max = 32767)
	{
		if ( is_object( $value ) ) return false;
		if ( is_array( $value ) ) return false;
		
		if ($min > $max) {
			$temp = $min;
			$min = $max;
			$max = $temp;
		}

		return (strlen($value) >= $min && strlen($value) <= $max);
	}



	/**
	* Wrapper for php's checkdnsrr function.
	*
	* @param string $host	Fully-Qualified Domain Name
	* @param string $type	Resource record type to lookup
	*						Supported types are: MX (default), A, AAAA, NS, TXT, CNAME
	*						Other types may work or may not work
	*
	* @return mixed  true if entry found,
	*                false if entry not found,
	*                null if this function is not supported by this environment
	*
	* Since null can also be returned, you probably want to compare the result
	* with === true or === false,
	*
	* @author bantu
	*/
	static function is_domain($host, $type = 'MX')
	{
		// The dot indicates to search the DNS root (helps those having DNS prefixes on the same domain)
		if (substr($host, -1) == '.')
		{
			$host_fqdn = $host;
			$host = substr($host, 0, -1);
		}
		else
		{
			$host_fqdn = $host . '.';
		}
		// $host		has format	some.host.example.com
		// $host_fqdn	has format	some.host.example.com.

		// If we're looking for an A record we can use gethostbyname()
		if ($type == 'A' && function_exists('gethostbyname'))
		{
			return (@gethostbyname($host_fqdn) == $host_fqdn) ? false : true;
		}

		// checkdnsrr() is available on Windows since PHP 5.3,
		// but until 5.3.3 it only works for MX records
		// See: http://bugs.php.net/bug.php?id=51844

		// Call checkdnsrr() if
		// we're looking for an MX record or
		// we're not on Windows or
		// we're running a PHP version where #51844 has been fixed

		// checkdnsrr() supports AAAA since 5.0.0
		// checkdnsrr() supports TXT since 5.2.4
		if (
			($type == 'MX' || DIRECTORY_SEPARATOR != '\\' || version_compare(PHP_VERSION, '5.3.3', '>=')) &&
			($type != 'AAAA' || version_compare(PHP_VERSION, '5.0.0', '>=')) &&
			($type != 'TXT' || version_compare(PHP_VERSION, '5.2.4', '>=')) &&
			function_exists('checkdnsrr')
		)
		{
			return checkdnsrr($host_fqdn, $type);
		}

		// dns_get_record() is available since PHP 5; since PHP 5.3 also on Windows,
		// but on Windows it does not work reliable for AAAA records before PHP 5.3.1

		// Call dns_get_record() if
		// we're not looking for an AAAA record or
		// we're not on Windows or
		// we're running a PHP version where AAAA lookups work reliable
		if (
			($type != 'AAAA' || DIRECTORY_SEPARATOR != '\\' || version_compare(PHP_VERSION, '5.3.1', '>=')) &&
			function_exists('dns_get_record')
		)
		{
			// dns_get_record() expects an integer as second parameter
			// We have to convert the string $type to the corresponding integer constant.
			$type_constant = 'DNS_' . $type;
			$type_param = (defined($type_constant)) ? constant($type_constant) : DNS_ANY;

			// dns_get_record() might throw E_WARNING and return false for records that do not exist
			$resultset = @dns_get_record($host_fqdn, $type_param);

			if (empty($resultset) || !is_array($resultset))
			{
				return false;
			}
			else if ($type_param == DNS_ANY)
			{
				// $resultset is a non-empty array
				return true;
			}

			foreach ($resultset as $result)
			{
				if (
					isset($result['host']) && $result['host'] == $host &&
					isset($result['type']) && $result['type'] == $type
				)
				{
					return true;
				}
			}

			return false;
		}

		// If we're on Windows we can still try to call nslookup via exec() as a last resort
		if (DIRECTORY_SEPARATOR == '\\' && function_exists('exec'))
		{
			@exec('nslookup -type=' . escapeshellarg($type) . ' ' . escapeshellarg($host_fqdn), $output);

			// If output is empty, the nslookup failed
			if (empty($output))
			{
				return NULL;
			}

			foreach ($output as $line)
			{
				$line = trim($line);

				if (empty($line))
				{
					continue;
				}

				// Squash tabs and multiple whitespaces to a single whitespace.
				$line = preg_replace('/\s+/', ' ', $line);

				switch ($type)
				{
					case 'MX':
						if (stripos($line, "$host MX") === 0)
						{
							return true;
						}
					break;

					case 'NS':
						if (stripos($line, "$host nameserver") === 0)
						{
							return true;
						}
					break;

					case 'TXT':
						if (stripos($line, "$host text") === 0)
						{
							return true;
						}
					break;

					case 'CNAME':
						if (stripos($line, "$host canonical name") === 0)
						{
							return true;
						}

					default:
					case 'A':
					case 'AAAA':
						if (!empty($host_matches))
						{
							// Second line
							if (stripos($line, "Address: ") === 0)
							{
								return true;
							}
							else
							{
								$host_matches = false;
							}
						}
						else if (stripos($line, "Name: $host") === 0)
						{
							// First line
							$host_matches = true;
						}
					break;
				}
			}

			return false;
		}

		return NULL;
	}

}
