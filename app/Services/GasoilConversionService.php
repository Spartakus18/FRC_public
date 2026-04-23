<?php

namespace App\Services;

class GasoilConversionService
{
    const REF_LITERS = 20;

    public static function literToCm(float $liters, float $capaciteCm): float
    {
        if ($liters <= 0 || $capaciteCm <= 0) {
            return 0;
        }

        return ($liters * $capaciteCm) / self::REF_LITERS;
    }

    public static function cmToLiter(float $cm, float $capaciteCm): float
    {
        if ($cm <= 0 || $capaciteCm <= 0) {
            return 0;
        }

        return ($cm * self::REF_LITERS) / $capaciteCm;
    }
}
