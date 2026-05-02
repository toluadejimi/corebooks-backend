<?php

namespace App\Enums;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Sales = 'sales';

    public function rank(): int
    {
        return match ($this) {
            self::Sales => 1,
            self::Manager => 2,
            self::Owner => 3,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * @param  self::Owner|self::Manager|self::Sales|null  $minimum
     */
    public static function normalize(?string $value): self
    {
        $v = strtolower((string) $value);

        return match ($v) {
            'owner', 'admin' => self::Owner,
            'manager' => self::Manager,
            'sales', 'cashier' => self::Sales,
            default => self::Sales,
        };
    }

    /**
     * @return list<string>
     */
    public static function assignableBy(self $actor): array
    {
        return match ($actor) {
            self::Owner => [self::Owner->value, self::Manager->value, self::Sales->value],
            self::Manager => [self::Manager->value, self::Sales->value],
            self::Sales => [],
        };
    }
}
