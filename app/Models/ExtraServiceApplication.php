<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraServiceApplication extends Model
{
    protected $fillable = [
        'uuid',
        'business_id',
        'extra_service_id',
        'status',
        'applicant_notes',
        'applicant_payload',
        'admin_notes',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function extraService(): BelongsTo
    {
        return $this->belongsTo(ExtraService::class, 'extra_service_id');
    }

    protected function casts(): array
    {
        return [
            'applicant_payload' => 'array',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'applicant_notes' => $this->applicant_notes,
            'applicant_payload' => $this->applicant_payload ?? [],
            'service' => $this->extraService?->toApiArray(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
