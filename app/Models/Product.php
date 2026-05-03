<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'business_id',
        'category_id',
        'uuid',
        'name',
        'image_url',
        'available_online',
        'gallery_urls',
        'variations',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'low_stock_threshold',
        'track_batches',
        'vat_rate',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'track_batches' => 'boolean',
            'vat_rate' => 'decimal:2',
            'available_online' => 'boolean',
            'gallery_urls' => 'array',
            'variations' => 'array',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
