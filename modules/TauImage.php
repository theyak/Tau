<?php
/**
 * Image module for TAU
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @docs            None!
 *
 */



class TauImage
{
	private $_filename = '';
	private $_width = 0;
	private $_height = 0;
	private $_mimetype = '';
	private $_image = null;
	
	/**
	 * Hash computed from averageHash() method.
	 */
	private $_hash = 0;
	
	private $error = '';
	
	function __construct($filenameOrResourceOrString)
	{
		$filename = $filenameOrResourceOrString;
		
		if (is_resource($filename))
		{
			$this->_width = imagesx($filename);
			$this->_height = imagesy($filename);
			$this->_image = $filename;
			return;
		}
		
		if (empty($filename))
		{
			throw new TauImageException(TauImageException::INVALID_FILE);
		}

		if (strlen($filename) < 1024)
		{
			$this->_filename = $filename;
			$size = getimagesize($filename);
			$this->_width = $size[0];
			$this->_height = $size[1];
			$this->_type = $size[2];
			$this->_mimetype = $size['mime'];
			
			switch ($this->_mimetype)
			{
				case 'image/jpeg':
					$this->_image = imagecreatefromjpeg($filename);
				break;

				case 'image/gif':
					$this->_image = imagecreatefromgif($filename);
				break;

				case 'image/png':
					$this->_image = imagecreatefrompng($filename);
				break;

				case 'image/x-windows-bmp':
					$this->_image = imagecreatefromwbmp($filename);
				break;		

				default:
					throw new TauImageException(TauImageException::INVALID_FILETYPE);				
			}			
		}
		else
		{
			// Perhaps a string was passed in
			$this->_image = @imagecreatefromstring($filename);
			if ($this->_image)
			{
				$this->_width = imagesx($this->_image);
				$this->_height = imagesy($this->_image);
				$this->_mimetype = self::getMimeTypeFromString($filename);
				if (!in_array($this->_mimetype, array('image/jpeg', 'image/gif', 'image/png', 'image/x-windows-bmp'))) {
					throw new TauImageException(TauImageException::INVALID_FILETYPE);					
				}
			}
			else
			{
				throw new TauImageException(TauImageException::INVALID_FILE);
			}
		}
	}
	
	/**
	 * Scale an image to a new size. Like resize, except this affects the current object
	 * instead of returning a new TauImage.
	 * 
	 * @param type $width
	 * @param type $height 
	 */
	public function setSize($width, $height)
	{
		$xscale = $this->_width / $width;
		$yscale = $this->_height / $height;

		if ($yscale > $xscale) {
			$new_width = round($this->_width * (1 / $yscale));
			$new_height = round($this->_height * (1 / $yscale));
		} else {
			$new_width = round($this->_width * (1 / $xscale));
			$new_height = round($this->_height * (1 / $xscale));
		}

		$destination = imagecreatetruecolor($width, $height);
		imagecopyresampled($destination, $this->_image, 0, 0, 0, 0, $width, $height, $this->_width, $this->_height);

		$this->destroy();
		$this->_image = $destination;
		$this->_width = $width;
		$this->_height = $height;
	}
	
	/**
	 * Proportionally scales an image to a specified height
	 * @param type $height The height to scale image to
	 */
	public function setSizeWithHeight($height = 150)
	{
		$width = $height * $this->getWidth() / $this->getHeight();
		$this->setSize($width, $height);
	}
	
	/**
	 * Proportionally scales an image to a specified height
	 * @param type $height The height to scale image to
	 */
	public function setSizeWithWidth($width = 150)
	{
		$height = $width * $this->getHeight() / $this->getWidth();
		$this->setSize($width, $height);
	}

	
	/**
	 * Scale an image to a new size
	 * @param type $width
	 * @param type $height
	 * @return TauImage TauImage instance with resized image
	 */
	public function resize($width, $height)
	{
		$xscale = $this->_width / $width;
		$yscale = $this->_height / $height;

		if ($yscale > $xscale) {
			$new_width = round($this->_width * (1 / $yscale));
			$new_height = round($this->height * (1 / $yscale));
		} else {
			$new_width = round($this->_width * (1 / $xscale));
			$new_height = round($this->_height * (1 / $xscale));
		}

		$destination = imagecreatetruecolor($width, $height);
		imagecopyresampled($destination, $this->_image, 0, 0, 0, 0, $width, $height, $this->_width, $this->_height);

		return new TauImage($destination);
	}
	
	public function square($size)
	{
		$src_w = $this->_width;
		$src_y = $this->_height;
		$ratio = $this->_width / $this->_height;
		$src_x = $src_y = 0;

		if($src_w > $src_h) {
            $src_x = ceil(($src_w - $src_h) / 2);
            $src_w = $src_h;
        } else {
            $src_y = ceil(($src_h - $src_w) / 2);
            $src_h = $src_w;
        }

		$destination = @imagecreatetruecolor($size, $size);
		@imagecopyresampled($destination, $this->_image, 0, 0, $src_x, $src_y, $size, $size, $src_w, $src_h);
		
		return new TauImage($destination);
	}
	
	/**
	 * Generates an HTML image tag with base 64 encoded data. Only works for modern browsers.
	 * @return string 
	 */
	public function toHtml()
	{
		ob_start();
		imagejpeg($this->getImageResource());
		$data = ob_get_clean();		

		$html = '<img src="data:image/jpeg;base64,' . base64_encode($data) . '">';
		return $html;
	}
	
	/**
	 * An extremely simple and relatively quick perceptual hash algorithm as described on
	 * http://www.hackerfactor.com/blog/index.php?/archives/432-Looks-Like-It.html.
	 * If you want to build a better one, such as one with discrete cosine transform, please
	 * do and contribute it! This one uses 8 bit true-grayscale images as opposed to 6-bit
	 * pseudo-greyscale images as described on hackerfactor.
	 * 
	 * @return type 
	 */
	function averageHash($size = 8)
	{
		$greyscaleImage = array();
		$averageValue = $finalHash = 0;
		$pixels = $size * $size;


		// Squeeze image down to 16x16
		$smallImage = imagecreatetruecolor($size, $size);  //  resample
		// $greyImage = imagecreatetruecolor($size, $size);  //  resample
		imagecopyresampled($smallImage, $this->_image, 0, 0, 0, 0, $size, $size, $this->_width, $this->_height);

		// Convert to greyscale
		for ($y = 0; $y < $size; $y++)
		{
			for ($x = 0; $x < $size; $x++)
			{
				$pixelColor = imagecolorat($smallImage, $x, $y);
				$r = ($pixelColor >> 16) & 0xFF;
				$g = ($pixelColor >> 8) & 0xFF;
				$b = $pixelColor & 0xFF;

				$greyTone = intval((299 * $r) + (587 * $g) + (114 * $b)) / 1000;

				$greyScaleImage[$x + ($y * $size)] = $greyTone;
				$averageValue += $greyTone;

				// $val = imagecolorallocate($greyImage, $greyTone, $greyTone, $greyTone) ;
				// imagesetpixel($greyImage, $x, $y, $val);
				// imagecolordeallocate($greyImage, $val);
			}
		}
	
		// Destroy the small image resources.
		imagedestroy($smallImage);
		// imagedestroy($greyImage);

		// Find average value of pixels.
		$averageValue /= $pixels;

		// Return 1-bits when the tone is equal to or above the average,
		// and 0-bits when it's below the average.
		$hash = str_repeat(0, $pixels);
		for ($k = 0; $k < $pixels; $k++)
		{
			if ($greyScaleImage[$k] >= $averageValue)
			{
				$hash[$pixels - 1 - $k] = '1';
			}
		}

		// Convert binary string to hex string
		$b = array(
			'0000' => '0', '0001' => '1', '0010' => '2', '0011' => '3',
			'0100' => '4', '0101' => '5', '0110' => '6', '0111' => '7',
			'1000' => '8', '1001' => '9', '1010' => 'A', '1011' => 'B',
			'1100' => 'C', '1101' => 'D', '1110' => 'E', '1111' => 'F',
		);
		$this->_hash = '';
		for ($i = 0; $i < strlen($hash); $i += 4)
		{
			$this->_hash .= $b[substr($hash, $i, 4)];
		}

		return $this->_hash;
	}

	public function getHeight()
	{
		return $this->_height;
	}
	
	public function getWidth()
	{
		return $this->_width;
	}
	
	public function getImageResource() 
	{
		return $this->_image;		
	}
	
	public function destroy()
	{
		imagedestroy($this->_image);
	}
	
	public function jpeg($file, $quality = 80)
	{
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0744, true);
		}
		imagejpeg($this->getImageResource(), $file, $quality);
	}

	public function png($file)
	{
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0744, true);
		}
		imagepng($this->getImageResource(), $file);
	}

	public function save($file)
	{
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0744, true);
		}

		$info = pathinfo($file);
		$basename = basename($file, '.' . $info['extension']);

		if ($this->_mimetype == 'image/gif') {
			imagegif($this->getImageResource(), dirname($file) . '/' . $basename . '.gif');
		} else if ($this->_mimetype == 'image/png') {
			imagepng($this->getImageResource(), dirname($file) . '/' . $basename . '.png');
		} else {
			imagejpeg($this->getImageResource(), dirname($file) . '/' . $basename . '.jpg', 80);
		}
	}

	// Source: http://stackoverflow.com/questions/2207095/get-image-mimetype-from-resource-in-php-gd
	public static function getMimeTypeFromString($binary)
	{
		if (
			!preg_match(
				'/\A(?:(\xff\xd8\xff)|(GIF8[79]a)|(\x89PNG\x0d\x0a)|(BM)|(\x49\x49(\x2a\x00|\x00\x4a))|(FORM.{4}ILBM))/',
				$binary, $hits
			)
		) {
			return 'application/octet-stream';
		}
		static $type = array (
			1 => 'image/jpeg',
			2 => 'image/gif',
			3 => 'image/png',
			4 => 'image/x-windows-bmp',
			5 => 'image/tiff',
			6 => 'image/x-ilbm',
		);
		return $type[count($hits) - 1];
	}

	public static function getExtensionFromString($binary)
	{
		if (
			!preg_match(
				'/\A(?:(\xff\xd8\xff)|(GIF8[79]a)|(\x89PNG\x0d\x0a)|(BM)|(\x49\x49(\x2a\x00|\x00\x4a))|(FORM.{4}ILBM))/',
				$binary, $hits
			)
		) {
			return '';
		}
		static $type = array (
			1 => 'jpg',
			2 => 'gif',
			3 => 'png',
			4 => 'bmp',
			5 => 'tiff',
			6 => 'ilbm', // I really don't know on this one.
		);
		return $type[count($hits) - 1];
	}

	public static function display($resource)
	{
		if ($resource instanceof TauImage) {
			$resource = $resource->getImageResource();
		}
		header('Content-Type: image/jpeg');
		imagejpeg($resource);
		exit;
	}

	
}

class TauImageException extends Exception
{
	const INVALID_FILE = -1;
	const INVALID_FILETYPE = -2;
	
	function __constuct($code)
	{
		parent::__construct("", $code);
	}
}