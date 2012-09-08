<?php
/**
 * View Module For TAU
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 * changelog:
 *   2011 Nov 18  Fix for detecting trailing slash in path name in addPath()
 *   2011 Nov 18  Remove default path of current folder as templates are rarely stored there
 */

if (!defined('TAU'))
{
	exit;
}

class TauView
{
	private $paths;
	private $variables;
	private $extensions = array('.phtml', '.php');

	function __construct()
	{
		$this->paths = array();
		$this->variables = array();
	}

	public function add_path($path, $pre = false) 
	{
		$this->addPath($path, $pre);
	}
	
	public function addPath($path, $pre = false)
	{
		if (!in_array(substr($path, -1), array('/', '\\'))) {
			$path .= DIRECTORY_SEPARATOR;
		}
		
		if (!in_array($path, $this->paths))
		{
			if ($pre) {
				array_unshift($this->paths, $path);
			} else {
				$this->paths[] = $path;
			}
		}
	}
	
	public function set_path($paths)
	{
		$this->setPath($paths);
	}

	public function setPath($paths)
	{
		if (!is_array($paths))
		{
			$paths = array($paths);
		}

		foreach ($paths AS $path)
		{
			self::add_path($path);
		}
	}

	public function getVariables() 
	{
		return $this->variables;
	}


	public function display($view, $variables = array())
	{
		extract($this->variables);
		extract($variables);
		
		$found = false;
		foreach ($this->paths AS $path)
		{
			foreach ($this->extensions AS $ext)
			{
				if (is_file($path . $view . $ext))
				{
					include $path . $view . $ext;
					$found = true;
					break;
				}
			}
			
			if ($found)
			{
				break;
			}

			if (is_file($path . $view))
			{
				include $path . $view;
				break;				
			}
		}
	}

	public function assign($name, $value)
	{
		$this->variables[$name] = $value;
	}
}
