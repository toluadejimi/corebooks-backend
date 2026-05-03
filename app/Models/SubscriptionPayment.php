<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'business_id',
        'subscription_plan_id',
        'user_id',
        'paystack_reference',
        'status',
        'amount_kobo',
        'authorization_url',
        'meta',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
