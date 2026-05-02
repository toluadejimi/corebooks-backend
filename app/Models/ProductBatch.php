<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'location_id',
        'uuid',
        'qty',
        'expiry_date',
        'cost_price_snapshot',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'expiry_date' => 'date',
            'cost_price_snapshot' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
