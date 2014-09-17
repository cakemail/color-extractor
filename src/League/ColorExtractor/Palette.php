<?php

namespace League\ColorExtractor;

class Palette implements \Countable, \IteratorAggregate {

    protected $colors;

    /**
     * @param int $color
     * @param int $count = 1
     * @return $this
     */
    public function addColor($color, $count = 1)
    {
        isset($this->colors[$color]) ?
            $this->colors[$color] += $count :
            $this->colors[$color] = $count;

        return $this;
    }

    /**
     * @param int $color
     * @return $this
     */
    public function removeColor($color)
    {
        unset($this->colors[$color]);

        return $this;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->colors = array();

        return $this;
    }

    /**
     * @return int
     */
    public function count() {
        return count($this->colors);
    }

    /**
     * @return int
     */
    public function getPixelCount()
    {
        return array_sum($this->colors);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->colors);
    }

    /**
     * @param int $color
     * @return int
     */
    public function getColorCount($color)
    {
        return $this->colors[$color];
    }

    /**
     * @param int $limit = null
     * @return array
     */
    public function getMostUsedColors($limit = null)
    {
        return array_slice($this->colors, 0, $limit, true);
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getMostRepresentativeColors($limit)
    {
        $colorsCount = count($this->colors);
        $colorScores = array();

        foreach($this->colors as $color=>$count) {
            $colorScores[$color] = self::getColorScore($color, $count, $colorsCount);
        }
        arsort($colorScores, SORT_NUMERIC);

        return array_keys(self::mergeColors($colorScores, $limit, 100/($limit + 1)));
    }


    /**
     * @param resource $image
     * @return Palette
     * @throws \InvalidArgumentException
     */
    public static function fromImage($image)
    {
        if (!is_resource($image) || get_resource_type($image) != 'gd') {
            throw new \InvalidArgumentException('Image must be a gd resource');
        }

        $palette = new Palette;
        $palette->colors = array();

        $isImageIndexed = !imageistruecolor($image);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        for ($x = 0; $x < $imageWidth; $x++) {
            for ($y = 0; $y < $imageHeight; $y++) {
                $color = imagecolorat($image, $x, $y);
                if ($isImageIndexed) {
                    $colorComponents = imagecolorsforindex($image, $color);
                    $color = ($colorComponents['red']*65536) + ($colorComponents['green']*256) + ($colorComponents['blue']);
                }

                $palette->addColor($color);
            }
        }

        arsort($palette->colors, SORT_NUMERIC);

        return $palette;
    }

    /**
     * @param int $color
     * @param bool $prependHash = true
     * @return string
     */
    public static function intColorToHex($color, $prependHash = true)
    {
        return ($prependHash ? '#' : '').sprintf('%02X%02X%02X', ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
    }

    /**
     * @param array $colors
     * @param int $limit
     * @param int $maxDelta=5
     * @return array
     */
    public static function mergeColors(array $colors, $limit, $maxDelta = 5)
    {
        $limit = min(count($colors), $limit);
        $labCache = array();
        $mergedColors = array();

        foreach($colors as $color => $colorCount) {
            if (empty($mergedColors)) {
                $mergedColors[$color] = $colorCount;
                continue;
            }

            $labColor = isset($labCache[$color]) ?
                $labCache[$color] :
                self::intColorToLab($color);

            $hasColorBeenMerged = false;

            foreach($mergedColors as $mergedColor => &$mergedColorCount) {
                $mergedLabColor = isset($labCache[$mergedColor]) ?
                    $labCache[$mergedColor] :
                    self::intColorToLab($mergedColor);

                if (self::ciede2000DeltaE($labColor, $mergedLabColor) < $maxDelta) {
                    $mergedColorCount += $colorCount;
                    $hasColorBeenMerged = true;
                    break;
                }
            }
            if (!$hasColorBeenMerged) {
                $mergedColors[$color] = $colorCount;
            }
            if (count($mergedColors) == $limit) {
                break;
            }
        }

        arsort($mergedColors, SORT_NUMERIC);

        return $mergedColors;
    }


    /**
     * @param int $color
     * @param int $count
     * @param int $colorsCount
     * @return float
     */
    protected static function getColorScore($color, $count, $colorsCount)
    {
        $srgbComponents = self::intColorToSrgb($color);
        $max = max($srgbComponents);
        $min = min($srgbComponents);
        $diff = $max - $min;
        $sum = $max + $min;
        $saturation = 0;
        if ($diff) {
            $saturation = $sum/2 > .5 ?
                $diff/(2 - $diff) :
                $diff/$sum;
        }
        $luminosity = (($sum/2) + (.2126*$srgbComponents['R']) + (.7152*$srgbComponents['G']) + (.0722*$srgbComponents['B']))/2;

        return $saturation < .5 ?
            (1 - $luminosity)*($count/$colorsCount) :
            $count*$saturation*$luminosity;
    }

    /**
     * @param int $color
     * @return array
     */
    protected static function intColorToLab($color)
    {
        return self::xyzToLab(
            self::srgbToXyz(
                self::intColorToSrgb($color)
            )
        );
    }

    /**
     * @param int $color
     * @return array
     */
    protected static function intColorToSrgb($color)
    {
        return self::rgbToSrgb(
            array(
                'R' => ($color >> 16) & 0xFF,
                'G' => ($color >> 8) & 0xFF,
                'B' => $color & 0xFF
            )
        );
    }

    /**
     * @param int $value
     * @return float
     */
    protected static function rgbToSrgbStep($value)
    {
        $value /= 255;

        return $value <= .03928 ?
            $value/12.92 :
            pow(($value + .055)/1.055, 2.4);
    }

    /**
     * @param array $rgb
     * @return array
     */
    protected static function rgbToSrgb($rgb)
    {
        return array(
            'R' => self::rgbToSrgbStep($rgb['R']),
            'G' => self::rgbToSrgbStep($rgb['G']),
            'B' => self::rgbToSrgbStep($rgb['B'])
        );
    }

    /**
     * @param array $rgb
     * @return array
     */
    protected static function srgbToXyz($rgb)
    {
        return array(
            'X' => (.4124564*$rgb['R']) + (.3575761*$rgb['G']) + (.1804375*$rgb['B']),
            'Y' => (.2126729*$rgb['R']) + (.7151522*$rgb['G']) + (.0721750*$rgb['B']),
            'Z' => (.0193339*$rgb['R']) + (.1191920*$rgb['G']) + (.9503041*$rgb['B'])
        );
    }

    /**
     * @param float $value
     * @return float
     */
    protected static function xyzToLabStep($value)
    {
        return $value > pow(6/29, 3) ? pow($value, 1/3) : (1/3)*pow(29/6, 2)*$value + 4/29;
    }

    /**
     * @param array $xyz
     * @return array
     */
    protected static function xyzToLab($xyz)
    {
        //http://en.wikipedia.org/wiki/Illuminant_D65#Definition
        $Xn = .95047;
        $Yn = 1;
        $Zn = 1.08883;

        // http://en.wikipedia.org/wiki/Lab_color_space#CIELAB-CIEXYZ_conversions
        return array(
            'L' => 116*self::xyzToLabStep($xyz['Y']/$Yn) - 16,
            'a' => 500*(self::xyzToLabStep($xyz['X']/$Xn) - self::xyzToLabStep($xyz['Y']/$Yn)),
            'b' => 200*(self::xyzToLabStep($xyz['Y']/$Yn) - self::xyzToLabStep($xyz['Z']/$Zn))
        );
    }

    /**
     * @param array $firstLabColor
     * @param array $secondLabColor
     *
     * @return float
     */
    protected static function ciede2000DeltaE($firstLabColor, $secondLabColor)
    {
        $C1 = sqrt(pow($firstLabColor['a'], 2) + pow($firstLabColor['b'], 2));
        $C2 = sqrt(pow($secondLabColor['a'], 2) + pow($secondLabColor['b'], 2));
        $Cb = ($C1 + $C2) / 2;

        $G = .5 * (1 - sqrt(pow($Cb, 7) / (pow($Cb, 7) + pow(25, 7))));

        $a1p = (1 + $G) * $firstLabColor['a'];
        $a2p = (1 + $G) * $secondLabColor['a'];

        $C1p = sqrt(pow($a1p, 2) + pow($firstLabColor['b'], 2));
        $C2p = sqrt(pow($a2p, 2) + pow($secondLabColor['b'], 2));

        $h1p = $a1p == 0 && $firstLabColor['b'] == 0 ? 0 : atan2($firstLabColor['b'], $a1p);
        $h2p = $a2p == 0 && $secondLabColor['b'] == 0 ? 0 : atan2($secondLabColor['b'], $a2p);

        $LpDelta = $secondLabColor['L'] - $firstLabColor['L'];
        $CpDelta = $C2p - $C1p;

        if ($C1p * $C2p == 0) {
            $hpDelta = 0;
        } elseif (abs($h2p - $h1p) <= 180) {
            $hpDelta = $h2p - $h1p;
        } elseif ($h2p - $h1p > 180) {
            $hpDelta = $h2p - $h1p - 360;
        } else {
            $hpDelta = $h2p - $h1p + 360;
        }

        $HpDelta = 2 * sqrt($C1p * $C2p) * sin($hpDelta / 2);

        $Lbp = ($firstLabColor['L'] + $secondLabColor['L']) / 2;
        $Cbp = ($C1p + $C2p) / 2;

        if ($C1p * $C2p == 0) {
            $hbp = $h1p + $h2p;
        } elseif (abs($h1p - $h2p) <= 180) {
            $hbp = ($h1p + $h2p) / 2;
        } elseif ($h1p + $h2p < 360) {
            $hbp = ($h1p + $h2p + 360) / 2;
        } else {
            $hbp = ($h1p + $h2p - 360) / 2;
        }

        $T = 1 - .17 * cos($hbp - 30) + .24 * cos(2 * $hbp) + .32 * cos(3 * $hbp + 6) - .2 * cos(4 * $hbp - 63);

        $sigmaDelta = 30 * exp(-pow(($hbp - 275) / 25, 2));

        $Rc = 2 * sqrt(pow($Cbp, 7) / (pow($Cbp, 7) + pow(25, 7)));

        $Sl = 1 + ((.015 * pow($Lbp - 50, 2)) / sqrt(20 + pow($Lbp - 50, 2)));
        $Sc = 1 + .045 * $Cbp;
        $Sh = 1 + .015 * $Cbp * $T;

        $Rt = -sin(2 * $sigmaDelta) * $Rc;

        return sqrt(
            pow($LpDelta / $Sl, 2) +
            pow($CpDelta / $Sc, 2) +
            pow($HpDelta / $Sh, 2) +
            $Rt * ($CpDelta / $Sc) * ($HpDelta / $Sh)
        );
    }
} 