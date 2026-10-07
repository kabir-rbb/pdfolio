<?php

namespace App\Services\Pdf;

use setasign\Fpdi\Fpdi;

/**
 * FPDI with support for rotated text (used for diagonal watermarks).
 * Rotation implementation follows the classic FPDF rotated-text recipe.
 */
class RotatableFpdi extends Fpdi
{
    protected float $extgstateAngle = 0;

    public function rotate(float $angle, float $x = -1, float $y = -1): void
    {
        if ($x == -1) {
            $x = $this->GetX();
        }
        if ($y == -1) {
            $y = $this->GetY();
        }
        if ($this->extgstateAngle != 0) {
            $this->_out('Q');
        }
        $this->extgstateAngle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
            ));
        }
    }

    public function rotatedText(float $x, float $y, string $txt, float $angle): void
    {
        $this->rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->rotate(0);
    }

    public function _endpage()
    {
        if ($this->extgstateAngle != 0) {
            $this->extgstateAngle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}
