<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosting extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CLOSED = 'closed';

    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_BUSINESS = 'business';

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'contract' => 'Contract',
        'internship' => 'Internship',
        'temporary' => 'Temporary',
    ];

    /**
     * 36 Nigerian states + FCT. Used by both web admin and mobile filters.
     *
     * @var array<int, string>
     */
    public const NIGERIAN_STATES = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue',
        'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu',
        'FCT - Abuja', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina',
        'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo',
        'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
    ];

    protected $fillable = [
        'uuid',
        'source',
        'submitted_by_business_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'title',
        'company_name',
        'description',
        'location_state',
        'location_city',
        'employment_type',
        'salary_min',
        'salary_max',
        'salary_period',
        'currency',
        'contact_email',
        'contact_phone',
        'apply_url',
        'status',
        'rejection_reason',
        'approved_at',
        'expires_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'approved_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function submitterBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'submitted_by_business_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
