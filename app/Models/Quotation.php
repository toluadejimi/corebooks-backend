<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quotation extends Model
{
    protected $fillable = [
        'business_id',
        'uuid',
        'number',
        'client_name',
        'client_email',
        'client_phone',
        'client_address',
        'status',
        'valid_until',
        'notes',
        'currency',
        'subtotal_ex_vat',
        'vat_total',
        'grand_total',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal_ex_vat' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quotation $q): void {
            if ($q->uuid === null || $q->uuid === '') {
                $q->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort_order');
    }
}
