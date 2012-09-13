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
 * @Author          theyak
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

	/**
	 * Flag indicating if in pipeline mode. When in pipeline mode, commands are queued
	 * up and send in one big write.
	 * @var bool
	 */
	private $pipeline = false;

	/**
	 * Commands queued for pipelining
	 * @var array
	 */
	private $pipeline_queue = array();

	public function __construct( $server = '127.0.0.1:6379' )
	{
		$this->server = $server;
	}


	public function close()
	{
		fclose($this->socket);
		$this->socket = null;
	}


	/**
	 * Perform an hgetall command
	 * @param string $key
	 * @return array
	 */
	public function hgetall($key)
	{		
		$result = $this->__call('hgetall', array($key));

		if (is_array($result) && sizeof($result) > 1)
		{
			for ($i = 0; $i < sizeof($result); $i += 2)
			{
				$array[$result[$i]] = $result[$i + 1];
			}
			return $array;
		}

		return $result;
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

		if ($this->pipeline)
		{
			$this->pipeline_queue[] = $cmd;
		}
		else
		{
			fwrite( $this->socket, $cmd );
			return $this->parseResponse();
		}
	}


	/**
	 * Start a pipeline session. When in pipeline mode, all commands are queued up
	 * and not sent to the server until pipeline_exec() is called
	 */
	public function pipeline()
	{
		$this->pipeline = true;
	}



	/**
	 * Execute all command in pipeline
	 * @return array Responses for each command
	 */
	public function pipeline_exec()
	{
		$count = sizeof( $this->pipeline_queue );
		foreach ( $this->pipeline_queue AS $command )
		{
			fwrite( $this->socket, $command );
		}

		$response = array();
		for ( $i = 0; $i < $count; $i++ )
		{
			$response[] = $this->parseResponse();
		}

		$this->pipeline_flush();
		return $response;
	}



	/**
	 * Clear the pipeline command queue and turn off the pipeline session
	 */
	public function pipeline_flush()
	{
		$this->pipline_queue = array();
		$this->pipeline = false;
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
