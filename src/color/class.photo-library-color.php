<?php


use ColorThief\ColorThief;

class PL_COLOR_HANDLER
{
    public function extractPalette($picture, $area = null): array
    {
        $palette = ColorThief::getPalette(
            $this->getSourceUrl(
                $picture,
                5,
                5,
                'array',
                $area
            )
        );
        return $palette;
    }


    /**
    * Calculation of the area to get the picture palette.
    *
    * @param int   $width
    * @param mixed $height
    * @return array|null
    */
    public function calcArea(int $width = 0, $height = 0)
    {

        if ($width < 1 || $height < 1) {
            return null;
        }

        $area = array(
            (($width / 3) * 2) + 20,
            $height * 0.75,
            $width,
            $height,
        );

        return $area;
    }

    public function calculateBrightness($color)
    {
        list($R, $G, $B) = $color;
        return (299 * $R + 587 * $G + 114 * $B) / 1000;
    }

    public function getBrightestColor(array $palette = array())
    {

        $brightnessArray = array_map(array( $this, 'calculateBrightness' ), $palette);

        $maxBrightnessIndex = array_reduce(
            array_keys($brightnessArray),
            function ($carry, $key) use ($brightnessArray) {
                return $brightnessArray[ $key ] > $brightnessArray[ $carry ] ? $key : $carry;
            },
            0
        );

        return $palette[ $maxBrightnessIndex ];
    }

    /**
     * Summary of getSourceUrl
     *
     * @param stdClass $picture
     * @return string
     */
    public function getSourceUrl(stdClass $picture): string
    {
        return urldecode(
            str_replace(
                'phototheque-wp.ddev.site',
                'www.photographie.stephanewagner.com',
                $picture->img_url
            )
        );
    }

}
