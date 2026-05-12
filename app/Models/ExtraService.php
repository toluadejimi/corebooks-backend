<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtraService extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'requirements',
        'icon_url',
        'application_form',
        'fee_amount_ngn',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount_ngn' => 'decimal:2',
            'is_active' => 'boolean',
            'application_form' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ExtraServiceApplication::class, 'extra_service_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'icon_url' => $this->icon_url,
            'application_form' => $this->application_form ?? [],
            'fee_amount_ngn' => (float) $this->fee_amount_ngn,
        ];
    }
}
