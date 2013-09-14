<?php


try {
    $c = new CunningCaptcha('157');
    $c->doit();
} catch (Exception $exc) {
    echo $exc->getTraceAsString();
    echo $exc->getMessage();
}


/**
 * This class basically represents just an area to draw on. It implements three
 * low level methods that we need to draw on the canvas:
 *  - plot_lineAA() which plots a anti-aliased line on the canvas.
 *  - plotCircleAA() plots a anti-aliased circle on the canvas.
 *  - plotEllipseRectAA() plots a anti-aliased ellipse on the canvas.
 */
class Canvas {
    protected $height, $width;
    protected $canvas = array(array()); /* Hide the canvas from the evil outside */
    /**
     * 
     * @param type $size
     * @param type $captcha_string
     */
    public function __construct($height=50, $width=50) {
        $this->height = $height;
        $this->width = $width;
        
        /* Initialize the canvas to draw on */
        for ($i = 0; $i < $this->height; $i++) {
            for ($j = 0; $j < $this->width; $j++) {
                $this->canvas[$i][$j] = '255 255 255'; /* White background. */
            }
        }
    }
    /**
     * 
     * @return type 2D array, which represents the canvas.
     */
    public function get_canvas() {
        return $this->canvas;
    }
    
    /**
     * Sets a pixel in the canvas.
     * @param type $x0
     * @param type $y0
     * @param type $c
     */
    private function set_pixel($x0, $y0, $c) {
        $c = (int)$c;
        if ($c <= 255 && $c > 0)
            $this->canvas[$y0][$x0] = "$c $c $c";
        else
            $this->canvas[$y0][$x0] = "0 0 0";
    }

    //
    // The following three functions are taken from http://members.chello.at/~easyfilter/bresenham.c
    // and ported to PHP. The logic itself stays unmodified. All the credits go to Zingl Alois.
    //
    /**
     * Draw a black (0) anti-aliased line on the 2d $this->canvas array.
     * @param type $x0
     * @param type $y0
     * @param type $x1
     * @param type $y1
     * @param type $wd
     */
    public function plot_lineAA($x0, $y0, $x1, $y1, $wd) {
        $dx = abs($x1 - $x0);
        $sx = $x0 < $x1 ? 1 : -1;
        $dy = abs($y1 - $y0);
        $sy = $y0 < $y1 ? 1 : -1;
        $err = $dx - $dy;
        $e2 = $x2 = $y2 = 0; /* error value e_xy */
        $ed = $dx + $dy == 0 ? 1 : sqrt((float) $dx * $dx + (float) $dy * $dy);

        for ($wd = ($wd + 1) / 2;;) { /* pixel loop */
            $this->set_pixel($x0, $y0, max(0, 255 * (abs($err - $dx + $dy) / $ed - $wd + 1)));
            $e2 = $err;
            $x2 = $x0;
            if (2 * $e2 >= -$dx) { /* x step */
                for ($e2 += $dy, $y2 = $y0; $e2 < $ed * $wd && ($y1 != $y2 || $dx > $dy); $e2 += $dx)
                    $this->set_pixel($x0, $y2 += $sy, max(0, 255 * (abs($e2) / $ed - $wd + 1)));
                if ($x0 == $x1)
                    break;
                $e2 = $err;
                $err -= $dy;
                $x0 += $sx;
            }
            if (2 * $e2 <= $dy) { /* y step */
                for ($e2 = $dx - $e2; $e2 < $ed * $wd && ($x1 != $x2 || $dx < $dy); $e2 += $dy)
                    $this->set_pixel($x2 += $sx, $y0, max(0, 255 * (abs($e2) / $ed - $wd + 1)));
                if ($y0 == $y1)
                    break;
                $err += $dx;
                $y0 += $sy;
            }
        }
    }

    /**
     * draw a black anti-aliased circle into the 2d $this->canvas array
     * @param type $xm
     * @param type $ym
     * @param type $r
     */
    public function plotCircleAA($xm, $ym, $r) {
        $x = -$r;
        $y = 0;           /* II. quadrant from bottom left to top right */
        $i = $x2 = $e2 = $err = 2 - 2 * $r; /* error of 1.step */
        $vr = 1 - $err;
        do {
            $i = 255 * abs($err - 2 * ($x + $y) - 2) / $r; /* get blend value of pixel */
            $this->set_pixel($xm - $x, $ym + $y, $i); /*   I. Quadrant */
            $this->set_pixel($xm - $y, $ym - $x, $i); /*  II. Quadrant */
            $this->set_pixel($xm + $x, $ym - $y, $i); /* III. Quadrant */
            $this->set_pixel($xm + $y, $ym + $x, $i); /*  IV. Quadrant */
            $e2 = $err;
            $x2 = $x; /* remember values */
            if ($err + $y > 0) { /* x step */
                $i = 255 * ($err - 2 * $x - 1) / $r; /* outward pixel */
                if ($i < 256) {
                    $this->set_pixel($xm - $x, $ym + $y + 1, $i);
                    $this->set_pixel($xm - $y - 1, $ym - $x, $i);
                    $this->set_pixel($xm + $x, $ym - $y - 1, $i);
                    $this->set_pixel($xm + $y + 1, $ym + $x, $i);
                }
                $err +=++$x * 2 + 1;
            }
            if ($e2 + $x2 <= 0) { /* y step */
                $i = 255 * (2 * $y + 3 - $e2) / $r; /* inward pixel */
                if ($i < 256) {
                    $this->set_pixel($xm - $x2 - 1, $ym + $y, $i);
                    $this->set_pixel($xm - $y, $ym - $x2 - 1, $i);
                    $this->set_pixel($xm + $x2 + 1, $ym - $y, $i);
                    $this->set_pixel($xm + $y, $ym + $x2 + 1, $i);
                }
                $err +=++$y * 2 + 1;
            }
        } while ($x < 0);
    }

    /**
     * Draw a black anti-aliased rectangular ellipse on the 2d $this->canvas array
     * @param type $x0
     * @param type $y0
     * @param type $x1
     * @param type $y1
     * @return type
     */
    public function plotEllipseRectAA($x0, $y0, $x1, $y1) {
        $a = abs($x1 - $x0);
        $b = abs($y1 - $y0);
        $b1 = $b & 1; /* diameter */
        $dx = 4 * ($a - 1.0) * $b * $b;
        $dy = 4 * ($b1 + 1) * $a * $a; /* error increment */
        $ed = $i = $err = $b1 * $a * $a - $dx + $dy; /* error of 1.step */
        $f;

        if ($a == 0 || $b == 0)
            return plot_lineAA($this->canvas, $x0, $y0, $x1, $y1);
        if ($x0 > $x1) {
            $x0 = $x1;
            $x1 += $a;
        } /* if called with swapped points */
        if ($y0 > $y1)
            $y0 = $y1; /* .. exchange them */
        $y0 += ($b + 1) / 2;
        $y1 = $y0 - $b1; /* starting pixel */
        $a = 8 * $a * $a;
        $b1 = 8 * $b * $b;

        for (;;) { /* approximate ed=sqrt(dx*dx+dy*dy) */
            $i = min($dx, $dy);
            $ed = max($dx, $dy);
            if ($y0 == $y1 + 1 && $err > $dy && $a > $b1)
                $ed = 255 * 4. / $a;           /* x-tip */
            else
                $ed = 255 / ($ed + 2 * $ed * $i * $i / (4 * $ed * $ed + $i * $i)); /* approximation */
            $i = $ed * abs($err + $dx - $dy);  /* get intensity value by pixel error */
            $this->set_pixel($x0, $y0, $i);
            $this->set_pixel($x0, $y1, $i);
            $this->set_pixel($x1, $y0, $i);
            $this->set_pixel($x1, $y1, $i);

            if ($f = 2 * $err + $dy >= 0) { /* x step, remember condition */
                if ($x0 >= $x1)
                    break;
                $i = $ed * ($err + $dx);
                if ($i < 255) {
                    $this->set_pixel($x0, $y0 + 1, $i);
                    $this->set_pixel($x0, $y1 - 1, $i);
                    $this->set_pixel($x1, $y0 + 1, $i);
                    $this->set_pixel($x1, $y1 - 1, $i);
                } /* do error increment later since values are still needed */
            }
            if (2 * $err <= $dx) { /* y step */
                $i = $ed * ($dy - $err);
                if ($i < 255) {
                    $this->set_pixel($x0 + 1, $y0, $i);
                    $this->set_pixel($x1 - 1, $y0, $i);
                    $this->set_pixel($x0 + 1, $y1, $i);
                    $this->set_pixel($x1 - 1, $y1, $i);
                }
                $y0++;
                $y1--;
                $err += $dy += $a;
            }
            if ($f) {
                $x0++;
                $x1--;
                $err -= $dx -= $b1;
            } /* x error increment */
        }
        if (--$x0 == $x1++) /* too early stop of flat ellipses */
            while ($y0 - $y1 < $b) {
                $i = 255 * 4 * abs($err + $dx) / $b1; /* -> finish tip of ellipse */
                $this->set_pixel($x0, ++$y0, $i);
                $this->set_pixel($x1, $y0, $i);
                $this->set_pixel($x0, --$y1, $i);
                $this->set_pixel($x1, $y1, $i);
                $err += $dy += $a;
            }
    }
}
/**
 * The CunningCaptcha class draws simple catpchas and saves them as ppm files.
 */
class CunningCaptcha extends Canvas {
    private $captcha_string;
    
    const legal_chars = '157ZQRE';
    
    /**
     * 
     * @param type $size
     * @param type $captcha_string
     */
    function __construct($captcha_string, $height=100, $width=300) {
        if (array_diff(str_split($captcha_string), str_split($this::legal_chars))) {
                throw new InvalidCaptchaCharsException(
                        'Your string may only use the following chars: '.$this::legal_chars
                );
        }
        parent::__construct($height, $width);
        $this->height = $height;
        $this->width = $width;
        $this->captcha_string = $captcha_string;
    }

    public function write_ppm_image($fname) {
        $h = fopen($fname . '.ppm', 'w');

        if (!$h) {
            var_dump(error_get_last());
            exit(1);
        }
        
        /* write the ppm header */
        if (!fwrite($h, sprintf("P3\n%d %d\n255\n", $this->width, $this->height))) {
            echo('Error: fwrite ppm header failed.');
            exit(1);
        }

        foreach ($this->canvas as $y_row) {
            foreach ($y_row as $pixel) {
                fwrite($h, $pixel . "\t");
            }
            fwrite($h, "\n");
        }

        if (!fclose($h)) {
            echo('Error: Couldn\'t close file.');
        }
    }

    public function test() {
        $c1 = new C_1(20, 20);
        $this->plot_char($c1->get_canvas(), 30, 60);
    }
    
    public function doit() {
        $this->test();
        $this->write_ppm_image(substr(md5($this->captcha_string), 0, 12));
    }
    
    /**
     * This function inserts the char into the captcha canvas at the 
     * position specified with the delta X and delta Y offset.
     * @param type $c The character to insert. Must be an 2D array.
     * @param int $dx The x offset where to begin plotting the char.
     * @param int $dy the y delta.
     */
    private function plot_char($c, $dx, $dy) {
        $width = count($c[0]); $height = count($c);
        if ($dy+$height > count($this->canvas) || $dx+$width > count($this->canvas[0])
                || $dy < 0 || $dx < 0) {
            return false; // Character is too big to plot in the canvas or is negative.
        } else {
            for ($i = 0; $i < $height; $i++) {
                for ($j = 0; $j < $width; $j++) {
                    $this->canvas[$dy+$i][$dx+$j] = $c[$i][$j];
                }
            }
        }
        return true;
    }
    
    /**
     * This function combines all the characters given by the
     * string and its order to form the captcha. There is a wide range
     * of parameters to control the randomization of the gluing
     * process. We can easily say that the sophistication (cunningness^^)
     * of this function along with the single chars determine the strength
     * against cracking attempts.
     */
    private function glue_characters() {
        /*$stamp = shuffle(array(
            new C_1(),
            new C_5(),
            new C_7(),
            new C_Z(),
            new C_E(),
            new C_Q(),
            new C_R()
        ));*/
  
    }
}   
class InvalidCaptchaCharsException extends Exception {}


abstract class Character extends Canvas {
    
    abstract protected function draw();
    
    public function __construct($height=50, $width=50) {
        parent::__construct($height, $width);
        $this->draw();
    }
    
    /**
     * 
     * @param type $level 0-3 Where 0 is no noise at all and 3 is a pretty unrecognizable char^^
     */
    public function apply_noise($level) {
        
    }
}

//
// These classes write the different letters.
//

class C_1 extends Character {
    protected function draw() {
        $this->plot_lineAA(0, $this->height/2, $this->width/2, 0, 3);
        $this->plot_lineAA($this->width/2, 0, $this->width/2, $this->height, 3);
    }
}

class C_5 extends Character {
    protected function draw() {
        
    }
}

class C_7 extends Character {
    protected function draw() {
        
    }
}

class C_Z extends Character {
    protected function draw() {
        
    }
}

class C_E extends Character {
    protected function draw() {
        
    }
}

class C_Q extends Character {
    protected function draw() {
        
    }
}

class C_R extends Character {
    protected function draw() {
        
    }
}
?>