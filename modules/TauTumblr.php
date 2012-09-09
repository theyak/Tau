<?php
/**
 * Tumblr.com module for TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Depends on      TauHttp, curl
 * @Project Page    None!
 * @docs            None!
 *
 */


/**
 * Example Usage:
$blog = "example.tumblr.com";
$tumblr = new TauTumblr($key);
$tumblr->getInfo($blog);
 *
$tumblr = new TauTumblr($key);
$tumblr->setBlog("example");
$tumblr->getPosts();
 *
// This example will display all images in a tumblr blog. You will have to set
// $api_key and $blog before running
include "Tau/Tau.php";

$tumblr = new TauTumblr($api_key, $blog);
$offset = 0;
do
{
	$posts = $tumblr->getPosts($tumblr->blog(), array('type' => 'photo', 'offset' => $offset));
	
	foreach ($posts->response->posts AS $key => $post)
	{
		foreach ($post->photos AS $photo)
		{
			echo '<img src="' . $photo->original_size->url . '"><br>' . $photo->original_size->url . '<br><br>';
		}
	}
	
	$total_posts = $posts->response->total_posts;
	$offset += sizeof($posts->response->posts);
} while ($offset < $total_posts);
 */



class TauTumblr
{
	private $key = "";
	private $blog = "";
	
	function __construct($blog = null, $key = null)
	{
		if (!is_null($key))
		{
			$this->setKey($key);
		}
		if (!is_null($blog))
		{
			$this->setBlog($blog);
		}
	}
	
	public function setKey($key)
	{
		$this->key = $key;
		return $this;
	}
	
	public function key($k = null)
	{
		if (is_null($k))
		{
			return $key;
		}
		return $this->setKey($key);
	}
	
	public function setBlog($blog)
	{
		$this->blog = $this->blogName($blog);
		return $this;
	}
	
	public function blog($blog = null)
	{
		if (is_null($blog))
		{
			return $this->blog;
		}
		return $this->setBlog($blog);
	}
	
	public function getInfo()
	{
		// $url = "http://api.tumblr.com/v2/blog/{$blog}/info?api_key={$this->key}";
		$url = 'http://' . $this->blog . '/api/read?num=1&json=1';

		$http = new TauHttp($url);
		$response = $http->fetch();
		$json = (substr($response, 22));
		$json = (substr($json, 0, -2));
		$result = json_decode($json);
		return $result->tumblelog;
	}
	
	public function getPosts($blog = null, $opt = array())
	{
		$posts = array();

		$offset = 0;
		$delta = 50;

		if (is_null($blog))
		{
			$blog = $this->blog;
		}
		
		$p = array(
			'start' => $offset,
			'num' => $delta,
		);

		$til = '';
		if (isset($opt['til']))
		{
			$til = $opt['til'];
			unset($opt['til']);
		}

		// Set type
		if (isset($opt['type']))
		{
			if (in_array($opt['type'], array('text', 'quote', 'photo', 'link', 'chat', 'video', 'audio'))) {
				$p['type'] = $opt['type'];
			}
			unset ($opt['type']);
		}

		$continue = true;
		do
		{
			$url = "http://{$blog}.tumblr.com/api/read/json?" . http_build_query($p, '', '&');
			$http = new TauHttp($url);
			$response = $http->fetch();
			if (strlen($response) < 50) {
				break;
			}
			$json = (substr($response, 22));
			$json = (substr($json, 0, -2));
			$result = json_decode($json);
			$array = (array)$result;

			foreach ($result->posts AS $post) {
				$post = (array)$post;
				if ($post['id'] == $til) {
					$continue = false;
					break;
				}
				$posts[] = $post;
			}

			$p['start'] += $delta;
		} while (sizeof($posts) < $array['posts-total'] && $continue);

		return $posts;
	}
	
	private function blogName($blog)
	{
		if (strpos($blog, '.') === false)
		{
			return $blog . '.tumblr.com';
		}
		return $blog;
	}
	
}
