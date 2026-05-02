<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'user_id',
        'basic_salary',
        'housing_allowance',
        'transport_allowance',
        'other_allowances',
        'gross_salary',
        'pension_employee',
        'pension_employer',
        'nhf',
        'paye',
        'cra_annual',
        'chargeable_income_annual',
        'net_salary',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowances' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'pension_employee' => 'decimal:2',
            'pension_employer' => 'decimal:2',
            'nhf' => 'decimal:2',
            'paye' => 'decimal:2',
            'cra_annual' => 'decimal:2',
            'chargeable_income_annual' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
