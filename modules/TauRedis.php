<?php
/**
 * An extremely simple Redis client for Tau.
 * It is safe to instantiate a server object even if no Redis calls are ever
 * performed. Connection to the server does not occur until a Redis
 * operation is called.
 * 
 * See the PHP section at http://www.redis.io/clients for more full-featured clients
 *
 * Based upon TinyRedisClient
 * https://github.com/ptrofimov/tinyredisclient
 *
 * Usage example:
 * include 'Tau/Tau.php'; // (or include 'Tau/Tau/modules/TauRedis.php')
 * $client = new TauRedis('server:port'); // Defaults to 127.0.0.1:6379
 * $client->set( 'key', 'value' );
 * $value = $client->get( 'key' );
 *
 * Full list of commands you can see on http://redis.io/commands
 *
 * @Author          levans
 * @Original        ptrofimov on GitHub
 * @Copyright       2012
 * @Depends on      None!
 * @Project Page    None!
 * @docs            None!
 * @link https://github.com/ptrofimov/tinyredisclient
 *
 */

class TauRedis
{
	private $socket = null;

	private $server = '127.0.0.1:6379';


	public function __construct( $server = '127.0.0.1:6379' )
	{
		$this->server = $server;
	}


	/**
	 * Magic method to call Redis commands
	 * @param <type> $method
	 * @param array $args
	 * @return <type>
	 */
	public function __call( $method, array $args )
	{
		// Push method name on to args stack at beginning
		array_unshift( $args, $method );

		$method = strtolower($method);
		$cmd = '';
		$count = 0;

		foreach ( $args as $item )
		{
			if ( is_array( $item ) )
			{
				if ( substr( $method, 0, 1 ) == 'h' )
				{
					foreach ( $item AS $key => $value )
					{
						$cmd .= '$' . strlen( $key ) . "\r\n" . $key . "\r\n";
						$cmd .= '$' . strlen( $value ) . "\r\n" . $value . "\r\n";
						$count += 2;
					}
				}
				else
				{
					foreach ( $item AS $value )
					{
						$cmd .= '$' . strlen( $value ) . "\r\n" . $value . "\r\n";
						$count++;
					}
				}
			}
			else
			{
				$cmd .= '$' . strlen( $item ) . "\r\n" . $item . "\r\n";
				$count++;
			}
		}

		if ( is_null( $this->socket ) )
		{
			$errno = 0;
			$errstr = '';
			$this->socket = @stream_socket_client( $this->server, $errno, $errstr, 5 );
			if ( ! $this->socket ) 
			{
				throw new Exception( "Redis connection to " . $this->server . " refused." );
			}
		}

		$cmd = '*' . $count . "\r\n" . $cmd;
		fwrite( $this->socket, $cmd );
		return $this->parseResponse();
	}


	/**
	 * Parse the respons from the Redis Server
	 * @return mixed
	 */
	private function parseResponse()
	{
		$line = fgets( $this->socket );
		list( $type, $result ) = array( $line[ 0 ], substr( $line, 1, strlen( $line ) - 3 ) );
		if ( $type == '-' )
		{
			throw new Exception( $result );
		}
		elseif ( $type == '$' )
		{
			if ( $result == -1 )
				$result = null;
			else
			{
				$line = fread( $this->socket, $result + 2 );
				$result = substr( $line, 0, strlen( $line ) - 2 );
			}
		}
		elseif ( $type == '*' )
		{
			$count = ( int ) $result;
			for( $i = 0, $result = array(); $i < $count; $i++ )
			{
				$result[] = $this->parseResponse();
			}
		}
		return $result;
	}
}
