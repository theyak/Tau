<?php
/**
 * Phpbb/MySQL Database Module For TAU
 * Allows use of scripts written for phpbb to be used with Tau.
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 *
 * changelog:
 *   1.0.0  Sep  8, 20122  Created
 */

if (!defined('TAU'))
{
	exit;
}

if (!class_exists('TauMysql'))
{
	include 'TauMysql.php';
}

class TauPhpbb extends TauMysql
{
	public function sql_close()
	{
		$this->close();
	}

	public function sql_query($sql, $ttl = 0)
	{
		return $this->query($sql, $ttl);
	}
	
	public function sql_affectedrows()
	{
		return $this->affectedRows();
	}

	public function sql_numrows()
	{
		return $this->numRows();
	}

	public function sql_fetchrow($resultSet = null)
	{
		return $this->fetch($resultSet);
	}

	public function sql_nextid()
	{
		return $this->insertId();
	}

	public function sql_freeresult($resultSet = null)
	{
		return $this->freeResult($resultSet);
	}

	public function sql_escape($msg)
	{
		return $this->escape($msg);
	}


	/**
	 * The following are not standard phpBB dbal functions
	 */
	
	public function sql_type_cast($value)
	{
		return $this->escape($value);
	}

	public function sql_escape_string($value)
	{
		return $this->stringify($value);
	}

	public function sql_insert($table, $fields)
	{
		return $this->insert($table, $fields);
	}

	public function sql_update($table, $fields, $where)
	{
		return $this->update($table, $fields, $where);
	}

	public function sql_fetch_all($sql)
	{
		return $this->fetchAll($sql);
	}

	public function sql_fetch_column($sql)
	{
		return $this->fetchColumn($sql);
	}

	public function sql_fetch_one($sql)
	{
		return $this->fetchOne($sql);
	}
}
