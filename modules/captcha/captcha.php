<?php

/**
 * CAPTCHA Support
 *
 * This module provides support CAPTCHA generation and verification for
 * other modules.
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class captcha extends Module {
	private static $_instance;
	private $options = array(
						'numbers',
						'lower',
						'upper',
						'numbers-lower',
						'upper-lower'
					);

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transfer_control($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'print_image_tag':
					$this->printImageTag($params, $children);
					break;

				case 'print_image':
					$this->printImage();
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	/**
	 * Checks validity of captcha against $value
	 *
	 * @param string $value
	 * @return boolean
	 */
	public function isCaptchaValid($value) {
		$result = false;

		if (!isset($_SESSION['captcha'])) return $result;

		$saved_value = fix_chars($_SESSION['captcha']);
		$result = ($saved_value == $value);

		return $result;
	}

	/**
	 * Removes captcha value from session data
	 * in order to prevent multiple tries
	 */
	public function resetCaptcha() {
		unset($_SESSION['captcha']);
	}

	/**
	 * Event called upon module initialisation
	 */
	public function initialize() {
		$this->save_setting('char_count', 4);
		$this->save_setting('char_type', 'numbers');
		$this->save_setting('arc_count', 15);
		$this->save_setting('font_size', 28);
		$this->save_setting('accepted_hosts', $_SERVER['SERVER_NAME']);
		$this->save_setting('colors', '#555555,#777777,#999999,#bbbbbb,#dddddd');
	}

	public function cleanup() {
	}

	/**
	 * Prints captcha image
	 */
	private function printImage() {
		// check if referer is in allowed list
		$value = $this->generateValue();
		$referer = $_SERVER['HTTP_REFERER'];
		$accepted_hosts = explode(',', $this->settings['accepted_hosts']);
		$arc_count = $this->settings['arc_count'];
		$char_count = $this->settings['char_count'];
		$font_size = $this->convertPXtoPT($this->settings['font_size']);
		$font_size_px = $this->settings['font_size'];
		$width = (10 + $this->settings['char_count'] * $this->settings['font_size']);
		$height = $font_size_px + 10;

		// create image
		$image = imagecreate($width, $height);
		// allocate colors and fonts
		$colors = $this->getColors($image);
		$fonts = $this->getFonts();

		// set random seed
		srand ((float) microtime() * 10000000);

		// background fill
		imagefill($image, 0, 0, $colors[0]);


		if (in_array(parse_url($referer, PHP_URL_HOST), $accepted_hosts)) {
			// draw specified number of circles
			for ($i=0; $i < $arc_count; $i++) {
				$arc_center_x = -15 + $i * 30 + rand(-20,20);
				$arc_center_y = round($height/2, 0) + rand(-20,20);
				$arc_width = ($width * 2) + rand(-40,40);
				$arc_height = ($height * 2) + rand(-40,40);
				$arc_color = $colors[ rand(1, count($colors) - 1) ];

				imagearc($image, $arc_center_x, $arc_center_y, $arc_width, $arc_height, 0, 360, $arc_color);
			}

			if (function_exists('imagefilter'))
				imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);

			// draw characters
			for ($i=0; $i < $char_count; $i++) {
				$font_angle = rand(-30, 30);
				$font_file = $fonts[ rand(0, count($fonts)-1) ];
				$font_color = $colors[ rand(1, count($colors) - 1) ];
				$font_x = 7 + ($font_size_px * $i);
				$font_y = (($height / 2) + ($font_size / 2) ) + rand(-4,+4);

				imagettftext($image, $font_size, $font_angle, $font_x, $font_y, $font_color, $font_file, $value[$i]);
			}
		}

		// print out the image
		header('Content-type: image/png');
		imagepng($image, null, 9, PNG_ALL_FILTERS);
		imagedestroy($image);
	}

	/**
	 * Retrieves list of suitable fonts found in predefined path
	 *
	 * @return array
	 */
	private function getFonts() {
		$path = $this->path.'include/';
		$result = array();

		if (is_dir($path)) {
			$dir_handle = opendir($path);

			while($file = readdir($dir_handle))
				if ($this->checkExtension($file, 'ttf'))
					$result[] = $path.$file;

			closedir($dir_handle);
		}

		return $result;
	}

	/**
	 * Checks if filename has valid extension
	 *
	 * @param string $filename
	 * @param string $ext
	 * @return boolean
	 */
	private function checkExtension($filename, $ext) {
		$info = pathinfo($filename, PATHINFO_EXTENSION);
		return $info == $ext;
	}

	/**
	 * Allocates colors for given image resource id
	 *
	 * @param resource $image
	 * @return array
	 */
	private function getColors($image) {
		$result = array();
		$colors = explode(',', $this->settings['colors']);

		foreach($colors as $color) {
			$RGB = $this->colorHEXtoRGB($color);
			$result[] = imagecolorallocate($image, $RGB[0], $RGB[1], $RGB[2]);
		}

		return $result;
	}

	/**
	 * Converts HEX formed color into RGB array
	 *
	 * @param string $rex
	 * @return array
	 */
	private function colorHEXtoRGB($hex) {
		if ($hex[0] != '#')
			$result = array(0,0,0); else
			$result = array(
							hexdec(substr($hex, 1, 2)),
							hexdec(substr($hex, 3, 2)),
							hexdec(substr($hex, 5, 2))
						);

		return $result;
	}

	/**
	 * Converts RGB array to HEX formed color
	 *
	 * @param integer $red
	 * @param integer $green
	 * @param integer $blue
	 * @return string
	 */
	private function colorRGBtoHEX($red, $green, $blue) {
		$result = '#'.dechex($red).dechex($green).dechex($blue);
		return $result;
	}

	/**
	 * Returns absolute image URL
	 * @return string
	 */
	private function getImageURL() {
		$result = URL::make_query($this->name, 'print_image');
		return $result;
	}

	/**
	 * Prints fully formed IMG tag
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	private function printImageTag($tag_params, $children) {
		$template = $this->load_template($tag_params, 'image.xml');
		$template->set_template_params_from_array($children);

		$params = array(
				'url'	=> $this->getImageURL(),
				'alt'	=> $this->get_language_constant('captcha_message')
			);

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Generates random character in given mode
	 *
	 * @param string $char_mode
	 * @return string
	 */
	private function getRandomChar($char_mode) {
		switch ($char_mode) {
			case 'numbers': 			$chartype = 1; break;
			case 'lower': 				$chartype = 2; break;
			case 'upper': 				$chartype = 3; break;
			case 'numbers-lower': 	$chartype = mt_rand(1,2); break;
			case 'upper-lower':		$chartype = mt_rand(2,3); break;
			default:						$chartype = mt_rand(1,3); break;
		}

		mt_srand((double)microtime()*1000000);

		switch ($chartype) {
			case 1: $randchar = mt_rand(49, 57); break;
			case 2: $randchar = mt_rand(97, 122); break;
			case 3: $randchar = mt_rand(65, 90); break;
		}

		return chr($randchar);
	}

	/**
	 * Generates captcha string of predefined size and type.
	 * Value is returned as string and saved as session variable
	 *
	 * @return string
	 */
	private function generateValue() {
		$value = '';

		for($i=0; $i < $this->settings['char_count']; $i++) {
			$char = $this->getRandomChar($this->settings['char_type']);
			$value .= $char;
		}

		$_SESSION['captcha'] = $value;
		return $value;
	}

	/**
	 * Converts points to pixels based on DPI
	 *
	 * @param integer $points
	 * @param integer $ptpi points per inch
	 * @param integer $pxpi pixels per inch
	 * @return integer
	 */
	private function convertPTtoPX($points, $ptpi=72, $pxpi=96) {
		return round(($points * $pxpi / $ptpi), 0);
	}

	/**
	 * Converts pixels to points based on DPI
	 *
	 * @param integer $pixels
	 * @param integer $ptpi points per inch
	 * @param integer $pxpi pixels per inch
	 * @return integer
	 */
	private function convertPXtoPT($pixels, $ptpi=72, $pxpi=96) {
		return round(($pixels * $ptpi / $pxpi), 0);
	}
}
