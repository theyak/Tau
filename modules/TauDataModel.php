<?php
/**
 * DB Model For Tau
 *
 * @Author          theyak
 * @Copyright       2013
 * @Project Page    None!
 * @Dependencies    None!
 * @Documentation   None!
 */


if (!defined('TAU'))
{
	exit;
}

class TauDataModel
{
	/**
	 * Schema for data. Should be an array in the form
	 *    'name' => $type
	 * Where $type is one of: int, string, real, float, double, string, datetime, json
	 * 
	 * An alternate form of array entry is:
	 *    'name' => array( 'default' => $value, 'type' => $type )
	 * Where $value is a default value for the data field.
	 * 
	 * @var array
	 */
	protected static $schema = array();
	
	
	/**
	 * Flag indicating if data has been loaded from database
	 * @var boolean
	 */
	public $loaded = false;


	/**
	 * The data storage. Should be one to one corelation to $schema after
	 * the constructor is run. It does not have to be set before the 
	 * constructor is run.
	 * @var array
	 */
	public $data = array();
	
	
	public function __construct( $data = null )
	{
		foreach ( static::$schema AS $field => $value )
		{
			$type = '';
			if ( is_array( $value ) ) 
			{
				if ( isset( $value[ 'default' ] ) )
				{
					$this->data[ $field ] = $value[ 'default' ];
				}
				else
				{
					$type = $value[ 'type' ];
				}
			}
			else
			{
				$type = $value;
			}

			if ( $type )
			{
				switch ( $type )
				{
					case 'int':
						$this->data[ $field ] = (int) 0;
					break;

					case 'real':
						$this->data[ $field ] = (double) 0.0;
					break;
				
					case 'boolean':
						$this->data[ $field ] = false;
					break;
				
					case 'datetime':
						$this->data[ $field ] = '0000-00-00 00:00:00';
					break;
				
					case 'json':
						$this->data[ $field ] = array();
					break;
					
					case 'string':
					default:
						$this->data[ $field ] = '';
					break;
				}
			}
		}

		if ( is_array( $data ) ) 
		{
			$this->fromDatabase( $data );
		}		
	}

	
	
	/**
	 * Converts a database row from TauDb::query to model data
	 *
	 * @param array $data
	 */
	protected function fromDatabase( $data )
	{
		foreach ( $data AS $field => $value ) 
		{	
			if ( isset( static::$schema[ $field ] ) )
			{
				if ( is_array( static::$schema[ $field ] ) )
				{
					$type = static::$schema[ $field ][ 'type' ];
				}
				else
				{
					$type = static::$schema[ $field ];
				}
	
				$this->data[ $field ] = $this->sanitize( $type, $value );
			}
		}
		
		$this->loaded = true;
	}

	

	/**
	 * Convert model data to data suitable for storing in database.
	 * 
	 * @return type
	 */
	protected function prepForSave()
	{
		$array = array();
		foreach( static::$schema AS $field => $type )
		{
			if ( is_array( $type ) )
			{
				$type = $type[ 'type' ];
			}
			
			$array[ $field ] = $this->sanitize( $type, $this->data[ $field ], true );
		}

		if ( isset( $this->data[ 'updated_at' ] ) )
		{
			$array[ 'updated_at' ] = date( 'Y-m-d H:i:s' );
		}
		if ( ! $this->loaded && isset( $this->data[ 'created_at' ] ) )
		{
			$array[ 'created_at' ] = date( 'Y-m-d H:i:s' );
		}
		
		return $array;
	}
	
	
	
	/**
	 * Set model data
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function __set( $key, $value )
	{
		if ( isset( static::$schema[ $key ] ) )
		{
			if ( is_array( static::$schema[ $key ] ) )
			{
				$type = static::$schema[ $key ][ 'type' ];
			}
			else
			{
				$type = static::$schema[ $key ];
			}
			$this->data[ $key ] = $this->sanitize( $type, $value );
		}		
	}

	
	
	/**
	 * Get model data
	 * @param string $key
	 * @return mixed null if data does not exist
	 */
	public function __get( $key )
	{
		if ( isset( $this->data[ $key ] ) )
		{
			return $this->data[ $key ];
		}
		
		return null;
	}

	
	
	/**
	 * Sanitize data according to its type
	 * 
	 * @param string $type
	 * @param mixed $value
	 * @param boolean $toDb If true, converts data to that suitable for storage in database
	 * 
	 * @return mixed
	 */
	protected function sanitize( $type, $value, $toDb = false )
	{
		switch ( $type )
		{
			case 'string':
				return (string) $value;
			break;

			case 'int':
				return (int) $value;
			break;
		
			case 'real':
			case 'float':
			case 'double':
				return (double) $value;
			break;

			case 'boolean':
				return (boolean) $value;
			break;

			case 'datetime':
				if ( ! $value )
				{
					return '0000-00-00 00:00:00';
				}
				return date( 'Y-m-d H:i:s', strtotime( $value ) );
			break;

			case 'json':
				if ( $toDb )
				{
					return json_encode( $value );
				}
				else
				{
					try {
						return json_decode( $value, true );
					} catch ( \Exception $ex ) {
						return array();
					}
				}
			break;
		}
	}
}
