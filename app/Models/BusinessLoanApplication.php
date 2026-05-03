<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BusinessLoanApplication extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'loan_partner_bank_id',
        'tax_id',
        'cac_registration_number',
        'cac_certificate_url',
        'additional_documents',
        'loan_amount_requested',
        'purpose',
        'business_summary',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'additional_documents' => 'array',
            'loan_amount_requested' => 'decimal:2',
            'reviewed_at' => 'datetime',
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

    public function partnerBank(): BelongsTo
    {
        return $this->belongsTo(LoanPartnerBank::class, 'loan_partner_bank_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::creating(function (BusinessLoanApplication $m): void {
            if ($m->uuid === null || $m->uuid === '') {
                $m->uuid = (string) Str::uuid();
            }
        });
    }
}
