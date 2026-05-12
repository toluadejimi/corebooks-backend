<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'phone',
        'email',
        'notes',
        'is_walk_in',
        'credit_enabled',
        'credit_limit',
        'credit_balance',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'is_walk_in' => 'boolean',
            'credit_enabled' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_balance' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function creditEntries(): HasMany
    {
        return $this->hasMany(CustomerCreditEntry::class);
    }
}
