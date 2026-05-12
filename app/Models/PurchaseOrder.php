<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'business_id',
        'supplier_id',
        'location_id',
        'uuid',
        'status',
        'total',
        'ordered_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'ordered_at' => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Scope purchase detail URLs to the workspace in the path (admin/b/{business}/purchases/…).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $business = request()->route('business');
        if ($business instanceof Business) {
            return static::query()
                ->where($field, $value)
                ->where('business_id', $business->id)
                ->firstOrFail();
        }

        return static::query()->where($field, $value)->firstOrFail();
    }
}
