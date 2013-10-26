<?php

error_reporting(E_ALL);

/* Tests */
$captcha = new Captcha();
$captcha->tests();


/* 
 * A simple class to represent points. Public members, since working with
 * getters and setters is too pendantic in this context.
 */
class Point {
	public $x;
	public $y;
	
	public function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
	
	public function __toString() {
		return 'Point(x='.$this->x.', y='.$this->y.')';
	}
}

/* 
 * I will NOT implement seperate data structures (classes) for splines and lines, 
 * because it just doesn't make sense. It would make things unnecessary complex and 
 * slow. Lines are just arrays of two points. Quadratic Bézier splines arrays of 
 * three points and cubic Bézier splines respectively arrays of 4 points.
 */


/*
 * Describes the class Canvas which implements algorithms to rasterize geometrical primitives such
 * as quadratic and cubic Bézier splines and straight lines. There may be more than one algorithm for each
 * shape.
 */
 
class Canvas {
	
	const STEP = 0.001;
	const NUM_SEGMENTS = 15;
	
	private $width;
	private $height;
	private $bitmap;
	
	/* Lookup-tables for Bézier coeffizients */
	private $quad_lut;
	private $cub_lut;
	
	public function __construct($width=100, $height=100) {
		$this->height = $height;
		$this->width = $width;
		$this->initbm();
	}
	
	private function initbm() {
		$this->bitmap = array_fill(0, $this->height, array_fill(0, $this->width, '255 255 255')); /* init the bitmap */
	}
	
	public function get_bitmap() {
		return $this->bitmap;
	}
	
	protected function set_pixel($x, $y, $color='0 0 0') {
		$this->bitmap[$y][$x] = $color;
	}
	
	/* All the different rasterization algorithms. They differ in performance and 
	 * granularity of drawing as well as the smoothness of the curve
	 */
	 
	/* The next two functions calculate the quadratic and cubic bezier points directly.
	 */
	
	private function _direct_quad_bez($p1, $p2, $p3) {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$x = intval($p1->x*$mt2 + $p2->x*2*$mt*$t + $p3->x*$t2);
			$y = intval($p1->y*$mt2 + $p2->y*2*$mt*$t + $p3->y*$t2);
			$this->set_pixel($x, $y);
			$t += self::STEP;
		}
	}
	
	private function _direct_cub_bez($p1, $p2, $p3, $p4) {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$x = intval($p1->x*$mt3 + 3*$p2->x*$mt2*$t + 3*$p3->x*$mt*$t2 + $p4->x*$t3);
			$y = intval($p1->y*$mt3 + 3*$p2->y*$mt2*$t + 3*$p3->y*$mt*$t2 + $p4->y*$t3);
			$this->set_pixel($x, $y);
			$t += self::STEP;
		}
	}
	
	/* Bézier plotting with look-up tables */
	
	private function _gen_quad_LUT() {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$this->quad_lut[] = array($mt2, 2*$mt*$t, $t2);
			$t += self::STEP;
		}
	}
	
	private function _gen_cub_LUT() {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$this->cub_lut[] = array($mt3, 3*$mt2*$t, 3*$mt*$t2, $t3);
			$t += self::STEP;
		}
	}
	
	private function _lut_quad_bez($p1, $p2, $p3) {
		if (!$this->quad_lut)
			$this->_gen_quad_LUT();
			
		foreach ($this->quad_lut as $c) {
			$x = intval($p1->x*$c[0] + $p2->x*$c[1] + $p3->x*$c[2]);
			$y = intval($p1->y*$c[0] + $p2->y*$c[1] + $p3->y*$c[2]);
			$this->set_pixel($x, $y);
		}
	}
	
	private function _lut_cub_bez($p1, $p2, $p3, $p4) {
		if (!$this->cub_lut)
			$this->_gen_cub_LUT();
			
		foreach ($this->cub_lut as $c) {
			$x = intval($p1->x*$c[0] + $p2->x*$c[1] + $p3->x*$c[2] + $p4->x*$c[3]);
			$y = intval($p1->y*$c[0] + $p2->y*$c[1] + $p3->y*$c[2] + $p4->y*$c[3]);
			$this->set_pixel($x, $y);
		}
	}
	
	/* The fastest one. Approximates the curve. */
	
	private function _approx_quad_bez($p1, $p2, $p3) {
		$lp = array();
		$lp[] = $p1;
		foreach (range(0, self::NUM_SEGMENTS) as $i) {
			$t = $i / self::NUM_SEGMENTS;
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$x = intval($p1->x*$mt2 + $p2->x*2*$mt*$t + $p3->x*$t2);
			$y = intval($p1->y*$mt2 + $p2->y*2*$mt*$t + $p3->y*$t2);
			$lp[] = new Point($x,$y);
		}	
		foreach (range(0, count($lp)-2) as $i)
			$this->line(array($lp[$i], $lp[$i+1]));
	}

	private function _approx_cub_bez($p1, $p2, $p3, $p4) {
		$lp = array();
		$lp[] = $p1;
		foreach (range(0, self::NUM_SEGMENTS) as $i) {
			$t = $i / self::NUM_SEGMENTS;
			$t2 = $t * $t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$x = intval($p1->x*$mt3 + 3*$p2->x*$mt2*$t + 3*$p3->x*$mt*$t2 + $p4->x*$t3);
			$y = intval($p1->y*$mt3 + 3*$p2->y*$mt2*$t + 3*$p3->y*$mt*$t2 + $p4->y*$t3);
			$lp[] = new Point($x,$y);
		}
		foreach (range(0, count($lp)-2) as $i)
			$this->line(array($lp[$i], $lp[$i+1]));
	}
	
	private function plot_casteljau($points) {
		foreach ($points as $p) {
			if (get_class($p) != 'Point')
				return False;
		}
		$t = 0;
		while ($t <= 1) {
			$this->_casteljau($points, $t);
			$t += self::STEP;
		}
	}
	
	/* Recursive, numerically stable implementation for plotting splines */
	private function _casteljau($points, $t) {
		/* Base case */
		if (count($points) == 1)
			$this->set_pixel($points[0]->x, $points[0]->y);
		else {
			$newpoints = array();
			foreach (range(0, count($points)-2) as $i) {
				$x = (1-$t) * $points[$i]->x + $t * $points[$i+1]->x;
				$y = (1-$t) * $points[$i]->y + $t * $points[$i+1]->y;
				$newpoints[] = new Point($x, $y);
			}
		$this->_casteljau($newpoints, $t);
		}
	}
	
	public function line($points) {
		if (count($points) != 2)
			return False;
		
		$x0 = $points[0]->x;
		$y0 = $points[0]->y;
		$x1 = $points[1]->x;
		$y1 = $points[1]->y;
		
		$dx = abs($x1-$x0);
		$dy = -abs($y1-$y0);
		$sx = $x0<$x1 ? 1 : -1;
		$sy = $y0<$y1 ? 1 : -1;
		$err = $dx+$dy;
		$e2 = 1;
		while (True) {
			$this->set_pixel($x0, $y0);
			if ($x0 == $x1 and $y0 == $y1)
				break;
			$e2 = 2*$err;
			if ($e2 >= $dy) {
				$err += $dy;
				$x0 += $sx;
			}
			if ($e2 <= $dx) {
				$err += $dx;
				$y0 += $sy;
			}
		}
	}
	
	public function spline($points, $algo='direct') {
		foreach ($points as $p) {
			if (get_class($p) != 'Point')
				return False;
		}
		
		if (!in_array($algo, array('direct', 'lut', 'approx', 'casteljau')))
			return False;
		
		/* Somehow ugly but what can you do? 
		 * Send me mail, in case you have a hint: admin [(at)] incolumitas.com
		 */
		switch ($algo) {
			case 'direct':
				if (count($points) == 3)
					$this->_direct_quad_bez($points[0], $points[1], $points[2]);
				if (count($points) == 4)
					$this->_direct_cub_bez($points[0], $points[1], $points[2], $points[3]);
				break;
			case 'lut':
				if (count($points) == 3)
					$this->_lut_quad_bez($points[0], $points[1], $points[2]);
				if (count($points) == 4)
					$this->_lut_cub_bez($points[0], $points[1], $points[2], $points[3]);
				break;
			case 'approx':
				if (count($points) == 3)
					$this->_approx_quad_bez($points[0], $points[1], $points[2]);
				if (count($points) == 4)
					$this->_approx_cub_bez($points[0], $points[1], $points[2], $points[3]);
			case 'casteljau':
				$this->plot_casteljau($points);
				break;
			default:
				break;
		}
	}
}

/*
 * The abstract class Glyph represents a generic Glyph. A glyph inherits from the class Canvas.
 * This class implements a wide range of different 'blur' techniques that try to confuse computational
 * approaches like OCR to recognize the glyph. Therefore there are linear transformations and a wide range of parameters that
 * are randomly chosen. All these bluring techniques can be applied with the blur() function.
 * Each concrete glyph (like A, b, y, x, Q) inherits from the abstract class Glyph. Each such concrete
 * class initializes the attribute glyphdata with the associative array of lines and bezier splines.
 */
 
class Glyph extends Canvas {
	
	protected $character;
	protected $glyphdata;
	
	public function __construct($character, $width, $height) {
		/* Array of array of Points() */
		$this->glyphdata = array();
	}
	
	/* This function load's the points that constitute the glyph. Maybe it's a design
	 * error, but a worse alternative would be to make n classes for each character where
	 * n = len(alphabet). This would imply a lot of redundant code and unflexible handling
	 */
	 private function get_glyph($c) {
		/* That'll be a freaking long switch statement */
		/* Theres a python function that generates this PHP switch statement,
		 * because the glyphdata may easily change if I make changes in the future.
		 */
		
		/* All Glyphs: y, W, G, a, H, i, f, b, n, S, X, k, E, Q */
		
		switch ($c) {
			case 'y':
				$this->glyphdata = array(
					'lines' => array(
						array(new Point(112, 375), new Point(147, 375)),
						array(new Point(147, 375), new Point(260, 601)),
						array(new Point(88, 698), new Point(82, 715)),
						array(new Point(378, 372), new Point(429, 372)),
						array(new Point(429, 372), new Point(429, 356)),
						array(new Point(429, 356), new Point(321, 356)),
						array(new Point(321, 356), new Point(321, 372)),
						array(new Point(321, 372), new Point(360, 372)),
						array(new Point(360, 372), new Point(271, 585)),
						array(new Point(271, 585), new Point(163, 375)),
						array(new Point(163, 375), new Point(217, 374)),
						array(new Point(217, 374), new Point(217, 356)),
						array(new Point(217, 356), new Point(112, 356)),
						array(new Point(112, 356), new Point(112, 375))
					),
					'cubic_splines' => array()
				);
				break;
			case 'W':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'G':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'a':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'H':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'i':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'f':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'b':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'n':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'S':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'X':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'k':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'E':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			case 'Q':
				$this->glyphdata = array(
					'lines' => array(),
					'cubic_splines' => array()
				);
				break;
			default:
				break;
		}
	 }
	 
	/* Rudimentary function to fill the glyph (only works when the glyph's shape
	 * is completly closed and the glyphs border are set to a specific color! The
	 * point to begin the filling process MUSTS be within the glyphs shape, otherwise
	 * the whole approach fails!
	 */
	private function _fill($startp) {}
	 
	
	/* linear transformations.
	 * These functions are on purpose in the Glyph class (could also be
	 * in Canvas) because they refer to the splines and lines that constitute
	 * a Glyph.  */
	
	private function _rotate($a) {}
	
	private function _skew($a) {}
	
	private function _scale($a) {}
	
	private function _shear($a) {}
	
	private function _translate($dx, $dy) {}
	
	public function blur() {
		
	}
}

******************** Glyph Data for y ********************
simple_lines
array(new Point(112, 375), new Point(147, 375)),
array(new Point(147, 375), new Point(260, 601)),
array(new Point(88, 698), new Point(82, 715)),
array(new Point(378, 372), new Point(429, 372)),
array(new Point(429, 372), new Point(429, 356)),
array(new Point(429, 356), new Point(321, 356)),
array(new Point(321, 356), new Point(321, 372)),
array(new Point(321, 372), new Point(360, 372)),
array(new Point(360, 372), new Point(271, 585)),
array(new Point(271, 585), new Point(163, 375)),
array(new Point(163, 375), new Point(217, 374)),
array(new Point(217, 374), new Point(217, 356)),
array(new Point(217, 356), new Point(112, 356)),
array(new Point(112, 356), new Point(112, 375)),
cubic_bezier
array(new Point(260, 601), new Point(260, 601), new Point(230, 670), new Point(204, 695)),
array(new Point(204, 695), new Point(191, 706), new Point(175, 717), new Point(158, 719)),
array(new Point(158, 719), new Point(135, 722), new Point(88, 698), new Point(88, 698)),
array(new Point(82, 715), new Point(82, 715), new Point(131, 737), new Point(155, 735)),
array(new Point(155, 735), new Point(175, 733), new Point(194, 721), new Point(210, 708)),
array(new Point(210, 708), new Point(240, 681), new Point(257, 642), new Point(277, 606)),
array(new Point(277, 606), new Point(317, 534), new Point(378, 372), new Point(378, 372)),
******************** Glyph Data for W ********************
simple_lines
array(new Point(70, 322), new Point(200, 712)),
array(new Point(200, 712), new Point(260, 712)),
array(new Point(260, 712), new Point(340, 442)),
array(new Point(340, 442), new Point(420, 712)),
array(new Point(420, 712), new Point(480, 712)),
array(new Point(480, 712), new Point(590, 322)),
array(new Point(590, 322), new Point(500, 332)),
array(new Point(500, 332), new Point(450, 612)),
array(new Point(450, 612), new Point(370, 402)),
array(new Point(370, 402), new Point(310, 402)),
array(new Point(310, 402), new Point(230, 612)),
array(new Point(230, 612), new Point(160, 332)),
array(new Point(160, 332), new Point(70, 322)),
******************** Glyph Data for G ********************
simple_lines
array(new Point(504, 590), new Point(506, 448)),
array(new Point(506, 448), new Point(386, 446)),
array(new Point(386, 446), new Point(384, 477)),
array(new Point(384, 477), new Point(475, 477)),
array(new Point(475, 477), new Point(475, 543)),
array(new Point(516, 306), new Point(516, 306)),
cubic_bezier
array(new Point(516, 306), new Point(516, 306), new Point(479, 274), new Point(457, 263)),
array(new Point(457, 263), new Point(437, 253), new Point(414, 246), new Point(392, 250)),
array(new Point(392, 250), new Point(366, 254), new Point(342, 271), new Point(324, 291)),
array(new Point(324, 291), new Point(301, 316), new Point(288, 349), new Point(280, 381)),
array(new Point(280, 381), new Point(271, 421), new Point(268, 464), new Point(280, 503)),
array(new Point(280, 503), new Point(289, 533), new Point(307, 561), new Point(332, 579)),
array(new Point(332, 579), new Point(351, 593), new Point(376, 598), new Point(400, 598)),
array(new Point(400, 598), new Point(435, 599), new Point(504, 590), new Point(504, 590)),
array(new Point(475, 543), new Point(476, 572), new Point(449, 570), new Point(426, 567)),
array(new Point(426, 567), new Point(398, 563), new Point(342, 547), new Point(326, 524)),
array(new Point(326, 524), new Point(306, 494), new Point(307, 454), new Point(311, 420)),
array(new Point(311, 420), new Point(314, 382), new Point(324, 341), new Point(348, 311)),
array(new Point(348, 311), new Point(364, 293), new Point(387, 280), new Point(412, 282)),
array(new Point(412, 282), new Point(445, 284), new Point(492, 330), new Point(492, 330)),
array(new Point(492, 330), new Point(492, 330), new Point(486, 338), new Point(516, 306)),
******************** Glyph Data for a ********************
simple_lines
array(new Point(401, 530), new Point(455, 91)),
array(new Point(315, 20), new Point(315, 20)),
array(new Point(372, 303), new Point(372, 303)),
cubic_bezier
array(new Point(315, 20), new Point(283, 20), new Point(253, 29), new Point(227, 47)),
array(new Point(227, 47), new Point(191, 73), new Point(180, 182), new Point(180, 182)),
array(new Point(180, 182), new Point(193, 197), new Point(215, 232), new Point(215, 232)),
array(new Point(215, 232), new Point(215, 232), new Point(224, 119), new Point(249, 92)),
array(new Point(249, 92), new Point(281, 51), new Point(327, 46), new Point(382, 94)),
array(new Point(382, 94), new Point(420, 150), new Point(397, 205), new Point(365, 248)),
array(new Point(365, 248), new Point(329, 297), new Point(271, 294), new Point(225, 307)),
array(new Point(225, 307), new Point(129, 346), new Point(120, 464), new Point(143, 541)),
array(new Point(143, 541), new Point(152, 582), new Point(173, 610), new Point(210, 621)),
array(new Point(210, 621), new Point(270, 638), new Point(313, 621), new Point(345, 583)),
array(new Point(345, 583), new Point(351, 610), new Point(352, 640), new Point(378, 646)),
array(new Point(378, 646), new Point(418, 654), new Point(471, 648), new Point(432, 609)),
array(new Point(432, 609), new Point(393, 571), new Point(403, 555), new Point(401, 530)),
array(new Point(455, 91), new Point(459, 63), new Point(411, 37), new Point(360, 25)),
array(new Point(360, 25), new Point(344, 21), new Point(329, 20), new Point(315, 20)),
array(new Point(372, 303), new Point(390, 387), new Point(371, 555), new Point(272, 591)),
array(new Point(272, 591), new Point(174, 628), new Point(155, 454), new Point(192, 404)),
array(new Point(192, 404), new Point(244, 333), new Point(298, 299), new Point(372, 303)),
******************** Glyph Data for H ********************
simple_lines
array(new Point(115, 172), new Point(115, 207)),
array(new Point(115, 207), new Point(170, 207)),
array(new Point(170, 207), new Point(170, 692)),
array(new Point(170, 692), new Point(115, 692)),
array(new Point(115, 692), new Point(115, 722)),
array(new Point(115, 722), new Point(265, 722)),
array(new Point(265, 722), new Point(265, 692)),
array(new Point(265, 692), new Point(210, 692)),
array(new Point(210, 692), new Point(210, 442)),
array(new Point(210, 442), new Point(440, 442)),
array(new Point(440, 442), new Point(440, 692)),
array(new Point(440, 692), new Point(380, 692)),
array(new Point(380, 692), new Point(380, 722)),
array(new Point(380, 722), new Point(535, 722)),
array(new Point(535, 722), new Point(535, 692)),
array(new Point(535, 692), new Point(485, 692)),
array(new Point(485, 692), new Point(485, 207)),
array(new Point(485, 207), new Point(535, 207)),
array(new Point(535, 207), new Point(535, 172)),
array(new Point(535, 172), new Point(380, 172)),
array(new Point(380, 172), new Point(380, 207)),
array(new Point(380, 207), new Point(440, 207)),
array(new Point(440, 207), new Point(440, 402)),
array(new Point(440, 402), new Point(210, 402)),
array(new Point(210, 402), new Point(210, 207)),
array(new Point(210, 207), new Point(265, 207)),
array(new Point(265, 207), new Point(265, 172)),
array(new Point(265, 172), new Point(115, 172)),
******************** Glyph Data for i ********************
simple_lines
array(new Point(300, 379), new Point(300, 852)),
array(new Point(300, 852), new Point(373, 852)),
array(new Point(373, 852), new Point(373, 379)),
array(new Point(373, 379), new Point(300, 379)),
array(new Point(344, 166), new Point(344, 166)),
cubic_bezier
array(new Point(344, 166), new Point(325, 165), new Point(305, 179), new Point(294, 194)),
array(new Point(294, 194), new Point(283, 210), new Point(277, 232), new Point(284, 250)),
array(new Point(284, 250), new Point(291, 272), new Point(314, 293), new Point(337, 295)),
array(new Point(337, 295), new Point(356, 296), new Point(376, 282), new Point(386, 265)),
array(new Point(386, 265), new Point(397, 247), new Point(399, 221), new Point(390, 202)),
array(new Point(390, 202), new Point(382, 184), new Point(363, 168), new Point(344, 166)),
******************** Glyph Data for f ********************
simple_lines
array(new Point(450, 182), new Point(450, 132)),
array(new Point(270, 302), new Point(210, 302)),
array(new Point(210, 302), new Point(210, 332)),
array(new Point(210, 332), new Point(270, 332)),
array(new Point(270, 332), new Point(270, 702)),
array(new Point(270, 702), new Point(210, 702)),
array(new Point(210, 702), new Point(210, 732)),
array(new Point(210, 732), new Point(340, 732)),
array(new Point(340, 732), new Point(360, 702)),
array(new Point(360, 702), new Point(300, 702)),
array(new Point(300, 702), new Point(300, 332)),
array(new Point(300, 332), new Point(360, 332)),
array(new Point(360, 332), new Point(360, 302)),
array(new Point(360, 302), new Point(300, 302)),
array(new Point(450, 182), new Point(450, 182)),
cubic_bezier
array(new Point(450, 132), new Point(450, 132), new Point(377, 132), new Point(348, 143)),
array(new Point(348, 143), new Point(316, 156), new Point(294, 180), new Point(280, 212)),
array(new Point(280, 212), new Point(267, 240), new Point(270, 302), new Point(270, 302)),
array(new Point(300, 302), new Point(300, 302), new Point(297, 248), new Point(307, 223)),
array(new Point(307, 223), new Point(316, 200), new Point(356, 180), new Point(380, 172)),
array(new Point(380, 172), new Point(407, 163), new Point(450, 182), new Point(450, 182)),
******************** Glyph Data for b ********************
simple_lines
array(new Point(234, 570), new Point(279, 279)),
array(new Point(279, 279), new Point(237, 275)),
array(new Point(263, 580), new Point(263, 580)),
cubic_bezier
array(new Point(237, 275), new Point(233, 288), new Point(232, 295), new Point(231, 301)),
array(new Point(231, 301), new Point(194, 577), new Point(199, 713), new Point(199, 713)),
array(new Point(199, 713), new Point(199, 713), new Point(336, 729), new Point(382, 689)),
array(new Point(382, 689), new Point(416, 660), new Point(431, 604), new Point(418, 562)),
array(new Point(418, 562), new Point(407, 529), new Point(371, 496), new Point(335, 495)),
array(new Point(335, 495), new Point(293, 495), new Point(234, 570), new Point(234, 570)),
array(new Point(263, 580), new Point(263, 580), new Point(212, 648), new Point(232, 673)),
array(new Point(232, 673), new Point(258, 706), new Point(325, 691), new Point(355, 663)),
array(new Point(355, 663), new Point(380, 641), new Point(383, 596), new Point(372, 564)),
array(new Point(372, 564), new Point(366, 547), new Point(350, 528), new Point(332, 528)),
array(new Point(332, 528), new Point(303, 526), new Point(263, 580), new Point(263, 580)),
******************** Glyph Data for n ********************
simple_lines
array(new Point(170, 332), new Point(170, 362)),
array(new Point(170, 362), new Point(220, 362)),
array(new Point(220, 362), new Point(220, 682)),
array(new Point(220, 682), new Point(170, 682)),
array(new Point(170, 682), new Point(140, 712)),
array(new Point(140, 712), new Point(300, 712)),
array(new Point(300, 712), new Point(300, 682)),
array(new Point(300, 682), new Point(250, 682)),
array(new Point(250, 682), new Point(251, 382)),
array(new Point(480, 442), new Point(480, 682)),
array(new Point(480, 682), new Point(430, 682)),
array(new Point(430, 682), new Point(430, 712)),
array(new Point(430, 712), new Point(560, 712)),
array(new Point(560, 712), new Point(510, 682)),
array(new Point(510, 682), new Point(510, 442)),
array(new Point(250, 352), new Point(250, 332)),
array(new Point(250, 332), new Point(170, 332)),
cubic_bezier
array(new Point(251, 382), new Point(251, 382), new Point(286, 370), new Point(346, 371)),
array(new Point(346, 371), new Point(407, 373), new Point(427, 385), new Point(444, 399)),
array(new Point(444, 399), new Point(458, 411), new Point(480, 442), new Point(480, 442)),
array(new Point(510, 442), new Point(510, 442), new Point(501, 403), new Point(480, 382)),
array(new Point(480, 382), new Point(450, 352), new Point(428, 347), new Point(360, 342)),
array(new Point(360, 342), new Point(291, 337), new Point(250, 352), new Point(250, 352)),
******************** Glyph Data for S ********************
simple_lines
array(new Point(531, 248), new Point(530, 182)),
array(new Point(185, 761), new Point(187, 829)),
array(new Point(471, 504), new Point(471, 504)),
cubic_bezier
array(new Point(471, 504), new Point(434, 427), new Point(325, 402), new Point(283, 327)),
array(new Point(283, 327), new Point(272, 306), new Point(262, 279), new Point(269, 256)),
array(new Point(269, 256), new Point(276, 234), new Point(300, 220), new Point(319, 209)),
array(new Point(319, 209), new Point(341, 196), new Point(366, 188), new Point(391, 186)),
array(new Point(391, 186), new Point(428, 182), new Point(472, 180), new Point(503, 201)),
array(new Point(503, 201), new Point(519, 211), new Point(532, 266), new Point(531, 248)),
array(new Point(530, 182), new Point(529, 159), new Point(538, 154), new Point(477, 153)),
array(new Point(477, 153), new Point(477, 153), new Point(345, 138), new Point(272, 196)),
array(new Point(272, 196), new Point(200, 254), new Point(211, 307), new Point(223, 334)),
array(new Point(223, 334), new Point(258, 415), new Point(367, 442), new Point(417, 515)),
array(new Point(417, 515), new Point(432, 538), new Point(440, 565), new Point(446, 592)),
array(new Point(446, 592), new Point(454, 631), new Point(456, 671), new Point(451, 710)),
array(new Point(451, 710), new Point(445, 747), new Point(449, 778), new Point(412, 817)),
array(new Point(412, 817), new Point(366, 865), new Point(268, 869), new Point(212, 833)),
array(new Point(212, 833), new Point(190, 819), new Point(184, 742), new Point(185, 761)),
array(new Point(187, 829), new Point(190, 883), new Point(251, 880), new Point(278, 880)),
array(new Point(278, 880), new Point(308, 879), new Point(337, 879), new Point(366, 879)),
array(new Point(366, 879), new Point(419, 878), new Point(471, 802), new Point(491, 743)),
array(new Point(491, 743), new Point(517, 668), new Point(506, 576), new Point(471, 504)),
******************** Glyph Data for X ********************
simple_lines
array(new Point(200, 322), new Point(320, 522)),
array(new Point(320, 522), new Point(190, 712)),
array(new Point(190, 712), new Point(230, 712)),
array(new Point(230, 712), new Point(340, 542)),
array(new Point(340, 542), new Point(450, 722)),
array(new Point(450, 722), new Point(490, 722)),
array(new Point(490, 722), new Point(360, 512)),
array(new Point(360, 512), new Point(486, 322)),
array(new Point(486, 322), new Point(450, 322)),
array(new Point(450, 322), new Point(340, 492)),
array(new Point(340, 492), new Point(240, 322)),
array(new Point(240, 322), new Point(200, 322)),
******************** Glyph Data for k ********************
simple_lines
array(new Point(141, 160), new Point(162, 166)),
array(new Point(162, 166), new Point(163, 618)),
array(new Point(163, 618), new Point(215, 605)),
array(new Point(215, 605), new Point(221, 574)),
array(new Point(221, 574), new Point(187, 590)),
array(new Point(187, 590), new Point(193, 477)),
array(new Point(193, 477), new Point(317, 455)),
array(new Point(317, 455), new Point(317, 502)),
array(new Point(317, 502), new Point(350, 545)),
array(new Point(350, 545), new Point(414, 621)),
array(new Point(414, 621), new Point(448, 594)),
array(new Point(448, 594), new Point(463, 532)),
array(new Point(188, 340), new Point(186, 148)),
array(new Point(186, 148), new Point(142, 143)),
array(new Point(142, 143), new Point(141, 160)),
array(new Point(201, 377), new Point(200, 453)),
array(new Point(200, 453), new Point(307, 435)),
array(new Point(335, 301), new Point(335, 301)),
cubic_bezier
array(new Point(463, 532), new Point(463, 532), new Point(440, 584), new Point(421, 587)),
array(new Point(421, 587), new Point(399, 591), new Point(390, 553), new Point(376, 536)),
array(new Point(376, 536), new Point(363, 520), new Point(339, 492), new Point(343, 473)),
array(new Point(343, 473), new Point(349, 435), new Point(387, 447), new Point(398, 410)),
array(new Point(398, 410), new Point(409, 378), new Point(431, 328), new Point(412, 300)),
array(new Point(412, 300), new Point(400, 280), new Point(400, 277), new Point(377, 278)),
array(new Point(377, 278), new Point(320, 281), new Point(188, 340), new Point(188, 340)),
array(new Point(335, 301), new Point(284, 311), new Point(202, 331), new Point(201, 377)),
array(new Point(307, 435), new Point(341, 429), new Point(372, 401), new Point(387, 370)),
array(new Point(387, 370), new Point(397, 352), new Point(404, 323), new Point(390, 307)),
array(new Point(390, 307), new Point(378, 293), new Point(353, 297), new Point(335, 301)),
******************** Glyph Data for E ********************
simple_lines
array(new Point(150, 202), new Point(150, 252)),
array(new Point(150, 252), new Point(200, 252)),
array(new Point(200, 252), new Point(200, 832)),
array(new Point(200, 832), new Point(150, 832)),
array(new Point(150, 832), new Point(150, 882)),
array(new Point(150, 882), new Point(520, 882)),
array(new Point(520, 882), new Point(520, 752)),
array(new Point(520, 752), new Point(470, 752)),
array(new Point(470, 752), new Point(470, 832)),
array(new Point(470, 832), new Point(250, 832)),
array(new Point(250, 832), new Point(250, 562)),
array(new Point(250, 562), new Point(430, 562)),
array(new Point(430, 562), new Point(430, 512)),
array(new Point(430, 512), new Point(250, 512)),
array(new Point(250, 512), new Point(250, 252)),
array(new Point(250, 252), new Point(470, 252)),
array(new Point(470, 252), new Point(470, 332)),
array(new Point(470, 332), new Point(520, 332)),
array(new Point(520, 332), new Point(520, 202)),
array(new Point(520, 202), new Point(150, 202)),
******************** Glyph Data for Q ********************
simple_lines
array(new Point(130, 722), new Point(200, 812)),
array(new Point(200, 812), new Point(490, 812)),
array(new Point(490, 812), new Point(540, 872)),
array(new Point(540, 872), new Point(630, 872)),
array(new Point(630, 872), new Point(570, 802)),
array(new Point(570, 802), new Point(640, 732)),
array(new Point(640, 732), new Point(640, 342)),
array(new Point(640, 342), new Point(561, 274)),
array(new Point(561, 274), new Point(200, 272)),
array(new Point(200, 272), new Point(130, 342)),
array(new Point(130, 342), new Point(130, 722)),
array(new Point(200, 712), new Point(240, 752)),
array(new Point(240, 752), new Point(440, 752)),
array(new Point(440, 752), new Point(400, 692)),
array(new Point(400, 692), new Point(490, 692)),
array(new Point(490, 692), new Point(530, 752)),
array(new Point(530, 752), new Point(570, 702)),
array(new Point(570, 702), new Point(570, 362)),
array(new Point(570, 362), new Point(520, 322)),
array(new Point(520, 322), new Point(250, 322)),
array(new Point(250, 322), new Point(200, 362)),
cubic_bezier
array(new Point(200, 362), new Point(198, 474), new Point(201, 680), new Point(200, 712)),

/*
 * The class Captcha represents the Captcha. It is on the top of the abstraction layer of this plugin
 * and is exported to the wordpress plugin. This means the wordpress code will do almost all it's stuff with 
 * a Captcha object. A captcha object has methods to write it's internal state as .bbm and .png files.
 * A object of this class can see as a "stamp", because Captcha implements a method called reload() that
 * recalculates a complete new captcha based on the current configuration. This means that the all applications only need one captcha object.
 * 
 * The Captcha class has a alphabet of glyphs and a array of glyphs (That it eventually exports as image). These array elements
 * are Glyph() instances. The glyphs are ony plotted when write_image() is called.
 */
 
class Captcha {
	private $width;
	private $height;
	
	public function __construct($width=400, $height=150) {
		$this->width = $width;
		$this->height = $height;
		$this->init_captcha();
	}
	
	public function tests() {
		/* Tests. */
		$c = new Canvas();
		$c->spline(array(new Point(10, 20), new Point(65, 70), new Point(40, 100)), $algo='lut');
		$c->spline(array(new Point(30, 25), new Point(0,0), new Point(46, 11)), $algo='casteljau');
		$c->spline(array(new Point(52, 18), new Point(40, 20), new Point(16, 31), new Point(100, 0)), $algo='direct');
		$c->spline(array(new Point(30, 80), new Point(40, 0), new Point(80, 80), new Point(80, 83)), $algo='approx');
		$this->copy_glyph(0, 0, $c->get_bitmap());
		$this->write_image('out', $format='ppm');
	}
	
	private function init_captcha() {
		$this->bitmap = array_fill(0, $this->height, array_fill(0, $this->width, '255 255 255')); /* init the bitmap */
	}
	
	/* 
	 * Copy the glyph data (two dimensional array) with the
	 * offset given by $dx and $dy into the bitmap of captcha.
	 * There might be a built-in function for this task such as array_merge()
	 * or something that is better.
	 */
	private function copy_glyph($dy=0, $dx=0, $glyphdata) {
		$height = count($glyphdata)-1;
		$width = count($glyphdata[0])-1;
		/* Check wheter the glyph fits */
		if ($height+$dy > $this->height || $width+$dx > $this->width)
			return False;
		/* If it fits I sits */
		foreach (range(0, $height) as $i) {
			foreach (range(0, $width) as $j) {
				$this->bitmap[$i+$dy][$j+$dx] = $glyphdata[$i][$j];
			}
		}
	}
	
	public function write_image($path, $format = 'png') {
		
		if (!in_array($format, array('png', 'ppm', 'jpg', 'gif')))
			return False;
		
		/* Either way we are going to write a file. When we cannot open a file, there
		 * must be a severe failure and we terminate the script.
		 */
		$h = fopen($path.".".$format, "w") or exit('Error: fopen() failed.');

		switch ($format) {
			case 'png':
				break;
			case 'ppm':
				/* Writes a colorous ppm image file. It's not compressed */
				fwrite($h, sprintf("P3\n%u %u\n255\n", $this->width, $this->height))
															or exit('fwrite() failed.');
				/* Write all pixels */
				foreach ($this->bitmap as $scanline) {
					foreach ($scanline as $pixel) {
						fwrite($h, $pixel."\t");
					}
					fwrite($h, "\n");
				}
				break;
			default:
				break;
		}
		
		/* Close the open file descriptor. */
		fclose($h) or exit('fclose() failed.');
	}
	
	public function reload() {}
}

?>
