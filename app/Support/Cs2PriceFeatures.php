<?php

namespace App\Support;

class Cs2PriceFeatures
{
    public static function empireEnabled(): bool
    {
        return filter_var(config('cs2price.empire_enabled', false), FILTER_VALIDATE_BOOL);
    }
}
