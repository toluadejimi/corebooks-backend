<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read float|null $purchase_orders_total
 */
class Supplier extends Model
{
    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'phone',
        'balance',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Resolve admin URLs like /admin/b/{business}/suppliers/{supplier}/edit to this workspace only.
     * (Avoids 404 when the same UUID exists elsewhere or binding resolves the wrong row.)
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
