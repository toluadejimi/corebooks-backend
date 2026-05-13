<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'business_id',
        'location_id',
        'gl_account_id',
        'uuid',
        'category',
        'amount',
        'notes',
        'paid_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Cash/bank account that funded this expense. */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class);
    }
}
