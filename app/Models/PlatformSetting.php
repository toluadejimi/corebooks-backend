<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->value('value');

        return $row !== null ? (string) $row : $default;
    }

    public static function getInt(string $key, int $default): int
    {
        $v = static::getValue((string) $key);

        if ($v === null || $v === '') {
            return $default;
        }

        return max(0, (int) $v);
    }

    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
