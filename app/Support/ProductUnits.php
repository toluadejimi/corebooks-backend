<?php

namespace App\Support;

final class ProductUnits
{
    /** @var list<string> */
    public const PRESETS = [
        'pcs',
        'kg',
        'g',
        'L',
        'ml',
        'pk',
        'pks',
        'box',
        'bag',
        'dozen',
        'carton',
        'roll',
        'pair',
        'set',
        'm',
        'cm',
        'lb',
        'oz',
    ];

    public static function normalize(?string $unit): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === '') {
            return 'pcs';
        }

        return substr($u, 0, 16);
    }
}
