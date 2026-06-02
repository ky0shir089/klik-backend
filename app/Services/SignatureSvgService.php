<?php

namespace App\Services;

class SignatureSvgService
{
    /**
     * Create a new class instance.
     */
    public function generateSignatureSvg($data)
    {
        $allX = [];
        $allY = [];

        foreach ($data as $stroke) {
            foreach ($stroke as $p) {
                $allX[] = $p[0];
                $allY[] = $p[1];
            }
        }

        $minX = min($allX);
        $maxX = max($allX);
        $minY = min($allY);
        $maxY = max($allY);

        $sigWidth  = $maxX - $minX;
        $sigHeight = $maxY - $minY;

        // box PDF area
        $boxWidth  = 300;
        $boxHeight = 120;
        $padding   = 10;

        // available area
        $usableW = $boxWidth - ($padding * 2);
        $usableH = $boxHeight - ($padding * 2);

        // keep ratio
        $scale = min($usableW / $sigWidth, $usableH / $sigHeight);

        $drawW = $sigWidth * $scale;
        $drawH = $sigHeight * $scale;

        // center position
        $offsetX = ($boxWidth - $drawW) / 2;
        $offsetY = ($boxHeight - $drawH) / 2;

        $path = '';

        foreach ($data as $stroke) {
            foreach ($stroke as $i => $p) {

                $x = (($p[0] - $minX) * $scale) + $offsetX;
                $y = (($p[1] - $minY) * $scale) + $offsetY;

                if ($i === 0) {
                    $path .= "M {$x} {$y} ";
                } else {
                    $path .= "L {$x} {$y} ";
                }
            }
        }

        return "
    <svg xmlns='http://www.w3.org/2000/svg'
         width='{$boxWidth}'
         height='{$boxHeight}'
         viewBox='0 0 {$boxWidth} {$boxHeight}'>

        <path d='{$path}'
              stroke='black'
              stroke-width='2'
              fill='none'
              stroke-linecap='round'
              stroke-linejoin='round'/>
    </svg>";
    }
}
