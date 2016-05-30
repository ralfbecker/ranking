<?php
/**
 * eGroupWare digital ROCK Rankings: generate topo grafik
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Measurement plugin for lead competitions
 */
class ranking_topo
{
	/**
	 * Width of arc to draw for holds
	 *
	 * @var int
	 */
	public static $arc_w = 20;
	/**
	 * Path of fontfile
	 *
	 * @var string
	 */
	public static $fontfile = '/Library/Fonts/Arial Bold.ttf';
	/**
	 * Size of font in px
	 *
	 * @var int
	 */
	public static $fontsize = 18;
	/**
	 * Array with all holds
	 *
	 * @var arrray
	 */
	public static $holds = array();

	/**
	 * Render topo image
	 *
	 * @param int|string $comp
	 * @param int|string $cat
	 * @param int $route
	 * @param int $place=1
	 * @param int $num=8
	 * @param int $width=1024
	 * @param int $height=null 4/3 ratio of $width
	 * @param int $margin=50 >0: distance of text from left or right page-border (incl. line to name), <0: distance from hold (no line!)
	 * @param int $icon='griff32' hold icon
	 * @param int $src=0 0: show scaled image, 1: show source image unscaled, 2: show source image with bounding boxes, 3: black background, no scaling
	 * @param int $topo=0
	 * @param boolean $png=true true: create png (with transparency for $src=3) or false: create jpeg image, default png
	 */
	public static function render($comp,$cat,$route,$place=1,$num=8,$width=1024,$height=null,
		$margin=50,$icon='griff32',$src=0,$topo=0,$png=true)
	{
		$start = microtime(true);

		$topos = ranking_measurement::get_topos($keys=array(
			'WetId' => $comp,
			'GrpId' => $cat,
			'route_order' => $route,
		), false, $comp_arr, $cat_arr);	// false = currently NO permission check, maybe we should do something IP based ...

		$path = $topos[$topo];
		if (!isset($path))
		{
			throw new egw_exception_wrong_parameter("Topo #$topo NOT found!");
		}

		// in case rkey and not id was used
		$keys['WetId'] = $comp_arr['WetId'];
		$keys['GrpId'] = $cat_arr['GrpId'];

		if (!($route = ranking_result_bo::$instance->route->read($keys)))
		{
			throw new egw_exception_wrong_parameter(lang('Route NOT found !!!'));
		}

		// src==3: no image in background for keying in liveimage
		if ($src == 3)
		{
			if (!($info = getimagesize(egw_vfs::PREFIX.$path)))
			{
				throw new egw_exception_wrong_parameter("Could not getimagesize('$path')!");
			}
			list($src_width, $src_height) = $info;

			if (!($src_image = imagecreatetruecolor($src_width, $src_height)))
			{
				throw new egw_exception_wrong_parameter("Could not imagecreatetruecolor($width, $height)!");
			}
			if ($png)	// do a png with transparent background (images needs to be filled with transparent color!)
			{
				imagesavealpha($src_image,true);
				$trans_color = imagecolorallocatealpha($src_image, 255, 255, 255, 127);
				imagefill($src_image, 0, 0, $trans_color);
			}
		}
		// topo image
		elseif (!($src_image = imagecreatefromjpeg(egw_vfs::PREFIX.$path)))
		{
			throw new egw_exception_wrong_parameter("Could not imagecreatefromjpeg('$path')!");
		}
		$src_width = imagesx($src_image);
		$src_height = imagesy($src_image);

		// query and draw holds
		self::$holds = ranking_measurement::get_holds($keys+array('hold_topo' => $topo));
		$color = imagecolorallocate($src_image, 255,   255,   0);
		$color2 = imagecolorallocatealpha($src_image, 255, 255, 0, 120);
		$current_color = imagecolorallocate($src_image, 255,   0,   0);
		// create destination image, if needed (src=0)
		if (!(int)$height) $height = (int)(3*$width/4);
		if (!$src && !($image = imagecreatetruecolor($width, $height)))
		{
			throw new egw_exception_wrong_parameter("Could not imagecreatetruecolor($width, $height)!");
		}

		list($x_min, $y_min, $x_max, $y_max) = $bbox = self::getAthleteBBox($keys, $route['current_1'], $num, $place, $ranking);
		$x_min *= $src_width/100.0;
		$x_max *= $src_width/100.0;
		$y_min *= $src_height/100.0;
		$y_max *= $src_height/100.0;
		//error_log(array2string($bbox)." w=$src_width, h=$src_height: $x_min, $y_min, $x_max, $y_max");
		if ($src == 2) imagerectangle($src_image, $x_min, $y_min, $x_max, $y_max, $color);

		// add an absolute margin (in px) around the bbox
		if (($x_min -= abs($margin)) < 0) $x_min = 0;
		if (($y_min -= 1.5*abs($margin)) < 0) $y_min = 0;
		if (($x_max += abs($margin)) >= $src_width) $x_max = $src_width-1;
		if (($y_max += 1.5*abs($margin)) >= $src_height) $y_max = $src_height-1;
		if ($src == 2) imagerectangle($src_image, $x_min, $y_min, $x_max, $y_max, $current_color);

		$bbox_w = $x_max-$x_min;
		$bbox_h = $y_max-$y_min;
		// check and fix aspect ratio ($width/$height) of our box
		if ($width/$height > $bbox_w/$bbox_h)	// our bbox is to high
		{
			$x_diff = $width/$height*$bbox_h - $bbox_w;
			if ($x_min > $x_diff/2)
			{
				$x_min -= $x_diff/2;
			}
			else
			{
				$x_min = 0;
			}
			$x_max = $x_min+$width/$height*$bbox_h-1;
			if ($x_max > $src_width-1)
			{
				$x_max = $src_width-1;
				$x_min = $x_max-$width/$height*$bbox_h;
			}
		}
		else	// our bbox is to wide
		{
			$y_diff = $height/$width*$bbox_w - $bbox_h;
			if ($y_min > $y_diff/2)
			{
				$y_min -= $y_diff/2;
			}
			else
			{
				$y_min = 0;
			}
			$y_max = $y_min+$height/$width*$bbox_w-1;
			if ($y_max > $src_height-1)
			{
				$y_max = $src_height-1;
				$y_min = $y_max-$height/$width*$bbox_w;
			}
		}
		if ($src)
		{
			if ($src == 2) imagerectangle($src_image, $x_min, $y_min, $x_max, $y_max, $color);

			// function to scale hold coordinates
			$getHoldXY = function($hold) use ($src_width, $src_height)
			{
				return ranking_topo::getHoldXY($hold, $src_width, $src_height);
			};

			// draw holds on destination
			self::showHolds($src_image, $getHoldXY, $icon);

			// add athletes on destination
			self::showAthletes($src_image, $src_width, $src_height, $getHoldXY, $color, $ranking, $margin, $route['current_1'], $current_color);

			$image = $src_image;
		}
		else
		{
			// copy and scale bounding box
			if (!imagecopyresampled($image, $src_image , 0, 0, $x_min , $y_min , $width , $height , $x_max-$x_min, $y_max-$y_min))
			{
				throw new egw_exception_wrong_parameter("Could not imagecopyresampled($image, $src_image , 0, 0, $src_x , $src_y , $width , $height , $src_width , $src_height)!");
			}
			// function to scale hold coordinates
			$getHoldXY = function($hold) use ($src_width, $src_height, $x_min, $y_min, $x_max, $y_max, $width, $height)
			{
				return ranking_topo::getHoldXY($hold, $src_width, $src_height, $x_min, $y_min, $x_max, $y_max, $width, $height);
			};

			// draw holds on destination
			self::showHolds($image, $getHoldXY, $icon);

			// add athletes on destination
			self::showAthletes($image, $width, $height, $getHoldXY, $color, $ranking, $margin, $route['current_1'], $current_color);
		}
		if ($png)
		{
			header('Content-Type: image/png');
			imagepng($image);
		}
		else
		{
			header('Content-Type: image/jpeg');
			imagejpeg($image);
		}
		error_log(__METHOD__."($comp,$cat,$route,...,$scale) ($src_width*$src_height) --> ($src_x, $src_y, $src_width*$src_height) --> ($width*$height) took ".number_format(microtime(true)-$start,2)." secs");
	}

	/**
	 * Draw holds
	 *
	 * @param resource $image
	 * @param callable $getHoldXY array(x,y) function(array $hold)
	 * @param string $icon='griff32' hold icon
	 * @param float $scale_hold=0.7
	 * @throws egw_exception_wrong_parameter
	 */
	public static function showHolds($image, $getHoldXY, $icon='griff32', $scale_hold=0.7)
	{
		// hold icon
		$hold_icon_path = EGW_SERVER_ROOT.'/'.str_replace($GLOBALS['egw_info']['server']['webserver_url'],'',common::find_image('ranking', $icon));
		if (!($hold_icon = imagecreatefrompng($hold_icon_path)))
		{
			throw new egw_exception_wrong_parameter("Could not imagecreatefrompng('$hold_icon_path')!");
		}
		$hold_w = imagesx($hold_icon);
		$hold_h = imagesy($hold_icon);

		// draw holds
		foreach(self::$holds as $hold)
		{
			list($x, $y) = $getHoldXY($hold);
			/* drawing circle with arbitrary color, but does NO antialiasing :-(
			imagesetthickness($src_image, 5);
			imagearc($src_image, $x, $y, self::$arc_w, self::$arc_w, 0, 359.9, $color2);	// 360 fails, because gd used imageellipse, which does not support thickness!
			imagesetthickness($src_image, 2);
			imagearc($src_image, $x, $y, self::$arc_w, self::$arc_w, 0, 359.9, $color);*/
			// using an image for the holds and opt. scale it
			if (!$scale_hold || $scale_hold == 1)
			{
				imagecopy($image, $hold_icon, $x-$hold_w/2, $y-$hold_h/2, 0, 0, $hold_w, $hold_h);
			}
			else
			{
				imagecopyresampled($image, $hold_icon, $x-$hold_w/2*$scale_hold, $y-$hold_h/2*$scale_hold, 0, 0, $hold_w*$scale_hold, $hold_h*$scale_hold, $hold_w, $hold_h);
			}
			//imagefttext($src_image, self::$fontsize, 0, $x+$hold_w/2+5, $y+self::$fontsize/2, $color, self::$fontfile, $hold['height']);
		}
	}

	/**
	 * Draw athletes from place $place to $place+num-1
	 *
	 * @param resource $image
	 * @param int $width
	 * @param int $height
	 * @param callable $getHoldXY array(x,y) function(array $hold)
	 * @param int $color color for participant name
	 * @param array $ranking
	 * @param int $margin=50 >0: distance of text from left or right page-border (incl. line to name), <0: distance from hold (no line!)
	 * @param int $current=null
	 * @param int $current_color=null color for text of current participant, default $color
	 */
	public static function showAthletes($image, $width, $height, $getHoldXY, $color, array $ranking, $margin=50, $current=null, $current_color=null)
	{
		$bgcolor = imagecolorallocatealpha($image, 128, 128, 128, 16);	// alpha: 0=full - 127=transparent, rounded corners are always 0=full!
		$bordercolor = imagecolorallocate($image, 64, 64, 64);
		$y_free = 0;	// to NOT write one athlete of the other
		$last_height='none';	// height of last athelete, to suppress multiple lines
		//imageantialias($image, true); does NOT work with imagethickness :-(
		foreach($ranking as $athlete)
		{
			$col = $athlete['PerId'] == $current && $current_color ? $current_color : $color;
			$text = ranking_result_bo::athlete2string($athlete, 'rank');

			if (($hold = self::getHoldsByHeight($athlete['result_plus'] == TOP_PLUS ? 999 : $athlete['result_height'])))
			{
				// position of hold
				list($x, $y) = $getHoldXY($hold);
				// boundingbox of text
				$box = imageftbbox(self::$fontsize, 0, self::$fontfile, $text);
				// position of text
				$xt = $margin > 0 ? ($x > $width/2 ? $margin : $width-$margin-$box[2]) : ($x > $width/2 ? $x+$margin-$box[2] : $x-$margin);
				$yt = $y+self::$fontsize/2;
				// check if there's already an other athlete written --> move further down
				if($yt-self::$fontsize-8 < $y_free)
				{
					$yt = $y_free+self::$fontsize+12;
				}
				// rectangle coordinates (using fontsize for y, so boxes are all the same hight, independend if text contains kerning like "g")
				$x1 = $box[6] + $xt - 8;
				$y1 = -self::$fontsize + $yt - 8;
				$x2 = $box[2] + $xt + 8;
				$y_free = $y2 = self::$fontsize/12 + $yt + 8;
				//error_log("box=".array2string($box)." fontsize=".self::$fontsize." --> upper-left: $x1, $y1, lower-right: $x2, $y2");

				// check if text is in window
				if ($y2 > $height) break;

				// draw rectangle
				imagesetthickness($image, 1);
				//imagefilledrectangle($image, $x1, $y1, $x2, $y2, $bgcolor);
				//imagerectangle($image, $x1, $y1, $x2, $y2, $bordercolor);
				self::round_corners($image, $x1, $y1, $x2, $y2, 7, $bordercolor, $bgcolor);

				// draw text
				imagefttext($image, self::$fontsize, 0, $xt, $yt, $col, self::$fontfile, $text);
				if ($margin > 0 && $last_height != $athlete['result_height'])
				{
					imagesetthickness($image, 3);
					//imagesetstyle($image, array($col, $col, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT));
					imageantialias($image, false);
					imageline($image, $x > $width/2 ? $x-16 : $x+16, $y, $x > $width/2 ? $x2+5 : $x1-5, ($y1+$y2)/2-1, $col);
					imageantialias($image, true);
					imageline($image, $x > $width/2 ? $x-16 : $x+16, $y-1, $x > $width/2 ? $x2+5 : $x1-5, ($y1+$y2-1)/2-1, $col);
					imageline($image, $x > $width/2 ? $x-16 : $x+16, $y+1, $x > $width/2 ? $x2+5 : $x1-5, ($y1+$y2-1)/2-1, $col);
					imageantialias($image, false);
					$last_height = $athlete['result_height'];
				}
			}
		}
	}

	/**
	 * Get bounding box of hold coordinates of selected athlets
	 *
	 * @param array $keys
	 * @param int $current=null
	 * @param int $num=8
	 * @param int $place=1
	 * @param array &$ranking=null on return ranking
	 * @return array ($x_min, $y_min, $x_max, $y_max) between 0.0 - 100.0 (in %)
	 */
	public static function getAthleteBBox(array $keys, $current=null, $num=3, $place=1, array &$ranking=null)
	{
		$ranking = self::getRanking($keys, $current, $num, $place);

		$x_min = $y_min = 100.0;
		$x_max = $y_max = 0;
		foreach($ranking as $athlete)
		{
			if (($hold = self::getHoldsByHeight($athlete['result_plus'] == TOP_PLUS ? 999 : $athlete['result_height'])))
			{
				$x = $hold['xpercent'];
				$y = $hold['ypercent'];

				if ($x < $x_min) $x_min = $x;
				if ($x > $x_max) $x_max = $x;
				if ($y < $y_min) $y_min = $y;
				if ($y > $y_max) $y_max = $y;
			}
		}
		return array($x_min, $y_min, $x_max, $y_max);
	}

	/**
	 * Get absolute coordinates for a hold
	 *
	 * @param array $hold
	 * @param int $src_width src width in px
	 * @param int $src_height src height in px
	 * @param int $x_min rectangle in src coord
	 * @param int $y_min
	 * @param int $x_max
	 * @param int $y_max
	 * @param int $width dest. width
	 * @param int $height dest. height
	 * @return array array($x, $y)
	 */
	static public function getHoldXY(array $hold, $src_width, $src_height,
		$x_min=null, $y_min=null, $x_max=null, $y_max=null, $width=null, $height=null)
	{
		$x = $src_width * $hold['xpercent'] / 100.0;
		$y = $src_height * $hold['ypercent'] / 100.0;

		// source coord 0..$src_width-1, 0..$src_height-1
		// dest. coord  0..$width-1, 0..$height-1
		if (!is_null($x_min))
		{
			$x = ($x - $x_min) * ($width-1) / ($x_max - $x_min);
			$y = ($y - $y_min) * ($height-1) / ($y_max - $y_min);
			//error_log(__METHOD__.'('.array2string($hold).", w=$src_width, h=$src_height, x=$x_min, y=$y_min, x=$x_max, y=$y_max, w=$width, h=$height) return array(x=$x, y=$y)");
		}
		//error_log(__METHOD__.'('.array2string($hold).", $src_width, $src_height) return array(x=$x, y=$y)");
		return array($x, $y);
	}

	/**
	 * Get a hold or all holds with a given height
	 *
	 * @param float|int $height
	 * @param boolean $first_match=true
	 * @return array of matching holds or just first hold if $first_match
	 */
	static public function getHoldsByHeight($height, $first_match=true)
	{
		$matches = array();
		foreach(self::$holds as $hold)
		{
			if ($hold['height'] == $height)
			{
				if ($first_match) return $hold;
				$matches[] = $hold;
			}
		}
		return $matches;
	}

	/**
	 * Get ranking of given participants ordered by rank
	 *
	 * @param array $keys array with values for keys 'WetId', 'GrpId', 'route_order'
	 * @param int $current=null include (current) participant specified by PerId
	 * @param int $num=3 return $num places (current participant not counted)
	 * @param int $place=1 return from place $place on
	 * @return array of athlete array
	 */
	static public function getRanking(array $keys, $current=null, $num=3, $place=1)
	{
		$athletes = array();
		if (($ranking = ranking_result_bo::$instance->route_result->search($keys,false,'result_rank ASC')))
		{
			foreach($ranking as $athlete)
			{
				if ($athlete['result_rank'] < $place) continue;
				if ($athlete['result_rank'] >= $place+$num && (!$current || isset($athletes[$current]))) break;

				if ($athlete['result_rank'] < $place+$num || $current && $athlete['PerId'] == $current)
				{
					$athletes[$athlete['PerId']] = $athlete;
				}
			}
		}
		return $athletes;
	}

	/**
	 * Init static vars
	 */
	public static function init_static()
	{
		include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_result_bo.inc.php');
		if (!isset(ranking_result_bo::$instance))
		{
			new ranking_result_bo();
		}
	}

	/**
	 * Draw a rectangle with rounded corners
	 *
	 * imagefillarc does NOT support alpha, therefore bgcolor should contain only a little alpha, eg. 10 looks still ok
	 *
	 * @param resource $image
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $radius
	 * @param int $color bordercolor
	 * @param int $bgcolor=0 background-color
	 */
	public static function round_corners($image,$x1,$y1,$x2,$y2,$radius,$color,$bgcolor=0,$radiusy=null)
	{
		if (is_null($radiusy)) $radiusy = $radius;
		if (2*$radiusy > $y2-$y1) $radiusy = (int)(($y2-$y1)/2);
		if (2*$radius > $x2-$x1)  $radius = (int)(($x2-$x1)/2);

		if ($bgcolor)	// background
		{
			// pies at corners
			imagefilledarc($image, $x1+$radius, $y1+$radiusy, 2*$radius, 2*$radiusy, 180, 270, $bgcolor, IMG_ARC_PIE);
			imagefilledarc($image, $x2-$radius, $y1+$radiusy, 2*$radius, 2*$radiusy, 270, 360, $bgcolor, IMG_ARC_PIE);
			imagefilledarc($image, $x2-$radius, $y2-$radiusy, 2*$radius, 2*$radiusy, 0, 90, $bgcolor, IMG_ARC_PIE);
			imagefilledarc($image, $x1+$radius, $y2-$radiusy, 2*$radius, 2*$radiusy, 90, 180, $bgcolor, IMG_ARC_PIE);

			// 3 rectangles filling the rest
			imagefilledrectangle($image, $x1+$radius+1, $y1, $x2-$radius-1, $y1+$radiusy-1, $bgcolor);
			imagefilledrectangle($image, $x1, $y1+$radius, $x2, $y2-$radiusy, $bgcolor);
			imagefilledrectangle($image, $x1+$radius+1, $y2-$radiusy+1, $x2-$radius, $y2-1, $bgcolor);
		}
		// border: 4 corners and lines inbetween
		imagearc($image, $x1+$radius, $y1+$radiusy, 2*$radius, 2*$radiusy, 180, 270, $color);
		imageline($image, $x1+$radius, $y1, $x2-$radius, $y1, $color);
		imagearc($image, $x2-$radius, $y1+$radiusy, 2*$radius, 2*$radiusy, 270, 360, $color);
		imageline($image, $x2, $y1+$radiusy, $x2, $y2-$radiusy, $color);
		imagearc($image, $x2-$radius, $y2-$radiusy, 2*$radius, 2*$radiusy, 0, 90, $color);
		imageline($image, $x1+$radius, $y2, $x2-$radius, $y2, $color);
		imagearc($image, $x1+$radius, $y2-$radiusy, 2*$radius, 2*$radiusy, 90, 180, $color);
		imageline($image, $x1, $y1+$radiusy, $x1, $y2-$radiusy, $color);
	}
}
ranking_topo::init_static();