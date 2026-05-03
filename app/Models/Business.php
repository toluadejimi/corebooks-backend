<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'logo_url',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'currency',
        'default_vat_rate',
        'tax_id',
        'settings',
        'version',
        'subscription_plan_id',
        'subscription_status',
        'subscription_trial_ends_at',
        'subscription_current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'default_vat_rate' => 'decimal:2',
            'subscription_trial_ends_at' => 'datetime',
            'subscription_current_period_end' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(BusinessLoanApplication::class);
    }

    public function extraServiceApplications(): HasMany
    {
        return $this->hasMany(ExtraServiceApplication::class);
    }
}
