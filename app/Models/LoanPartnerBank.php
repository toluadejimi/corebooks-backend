<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanPartnerBank extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'logo_url',
        'min_amount_ngn',
        'max_amount_ngn',
        'notes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount_ngn' => 'decimal:2',
            'max_amount_ngn' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(BusinessLoanApplication::class, 'loan_partner_bank_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_url' => $this->logo_url,
            'min_amount_ngn' => (float) $this->min_amount_ngn,
            'max_amount_ngn' => (float) $this->max_amount_ngn,
        ];
    }

    public function amountIsAllowed(float|string $amountNgn): bool
    {
        $a = (float) $amountNgn;

        return $a >= (float) $this->min_amount_ngn && $a <= (float) $this->max_amount_ngn;
    }
}
