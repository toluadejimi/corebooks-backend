<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobSeeker extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_ARCHIVED = 'archived';

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full time',
        'part_time' => 'Part time',
        'contract' => 'Contract',
        'internship' => 'Internship',
        'temporary' => 'Temporary',
    ];

    /** 36 Nigerian states + FCT. Mirrors JobPosting list. */
    public const NIGERIAN_STATES = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue',
        'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu',
        'FCT - Abuja', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina',
        'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo',
        'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
    ];

    protected $fillable = [
        'uuid',
        'full_name',
        'headline',
        'email',
        'phone',
        'photo_url',
        'cv_url',
        'cv_filename',
        'location_state',
        'location_city',
        'open_to_relocate',
        'years_experience',
        'employment_type',
        'expected_salary_min',
        'expected_salary_max',
        'salary_period',
        'currency',
        'about',
        'skills',
        'education',
        'linkedin_url',
        'status',
        'created_by_user_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'open_to_relocate' => 'boolean',
            'expected_salary_min' => 'decimal:2',
            'expected_salary_max' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function businessesShortlisting(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_seeker_shortlists')
            ->withTimestamps()
            ->withPivot(['note', 'added_by_user_id']);
    }
}
