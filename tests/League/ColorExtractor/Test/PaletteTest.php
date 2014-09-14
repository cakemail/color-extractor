<?php

namespace League\ColorExtractor\Test;

use League\ColorExtractor\Palette;

class ImageTest extends \PHPUnit_Framework_TestCase
{
    protected $jpegPath = './tests/assets/test.jpeg';
    protected $gifPath = './tests/assets/test.gif';
    protected $pngPath = './tests/assets/test.png';

    public function testJpegExtractSingleColor()
    {
        $palette = Palette::fromImage(imagecreatefromjpeg($this->jpegPath));
        $colors = $palette->getMostRepresentativeColors(1);

        $this->assertInternalType('array', $colors);
        $this->assertCount(1, $colors);
        $this->assertEquals('#F3EC18', $colors[0]);
    }

    public function testGifExtractSingleColor()
    {
        $palette = Palette::fromImage(imagecreatefromgif($this->gifPath));
        $colors = $palette->getMostRepresentativeColors(1);

        $this->assertInternalType('array', $colors);
        $this->assertCount(1, $colors);
        $this->assertEquals('#B772DB', $colors[0]);
    }

    public function testPngExtractSingleColor()
    {
        $palette = Palette::fromImage(imagecreatefrompng($this->pngPath));
        $colors = $palette->getMostRepresentativeColors(1);

        $this->assertInternalType('array', $colors);
        $this->assertCount(1, $colors);
        $this->assertEquals('#FE6900', $colors[0]);
    }

    public function testJpegExtractMultipleColors()
    {
        $palette = Palette::fromImage(imagecreatefromjpeg($this->jpegPath));
        $numColors = 3;
        $colors = $palette->getMostRepresentativeColors($numColors);

        $this->assertInternalType('array', $colors);
        $this->assertCount($numColors, $colors);
        $this->assertEquals($colors, array('#F3EC18', '#F49225', '#E82E31'));
    }
}
