<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_amount_kobo',
        'billing_interval',
        'max_records',
        'features',
        'feature_inventory',
        'feature_accounting_reports',
        'feature_tax_reports',
        'feature_database_backup',
        'feature_business_loan',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'feature_inventory' => 'boolean',
            'feature_accounting_reports' => 'boolean',
            'feature_tax_reports' => 'boolean',
            'feature_database_backup' => 'boolean',
            'feature_business_loan' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'subscription_plan_id');
    }

    public function priceNaira(): float
    {
        return round($this->price_amount_kobo / 100, 2);
    }

    public function toApiArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price_ngn' => $this->priceNaira(),
            'price_amount_kobo' => $this->price_amount_kobo,
            'billing_interval' => $this->billing_interval,
            'max_records' => $this->max_records,
            'features' => $this->features ?? [],
            'feature_flags' => [
                'inventory' => $this->feature_inventory,
                'accounting_reports' => $this->feature_accounting_reports,
                'tax_reports' => $this->feature_tax_reports,
                'database_backup' => $this->feature_database_backup,
                'business_loan' => $this->feature_business_loan,
            ],
        ];
    }
}
