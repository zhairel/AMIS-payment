<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EnrollmentApplicant extends Model
{
    protected static function booted()
    {
        static::saving(function ($applicant) {
            if ($applicant->isDirty('medical_has_concern') && $applicant->medical_has_concern === 'No') {
                if ($applicant->medical_record_url) {
                    Storage::disk('public')->delete($applicant->medical_record_url);
                    if (str_contains($applicant->medical_record_url, '/optimized/')) {
                        Storage::disk('public')->delete(str_replace('/optimized/', '/original/', $applicant->medical_record_url));
                        Storage::disk('public')->delete(str_replace('/optimized/', '/thumbnails/small/', $applicant->medical_record_url));
                        Storage::disk('public')->delete(str_replace('/optimized/', '/thumbnails/medium/', $applicant->medical_record_url));
                        Storage::disk('public')->delete(str_replace('/optimized/', '/thumbnails/large/', $applicant->medical_record_url));
                    }
                    $applicant->medical_record_url = null;
                }
                
                // Clear document_statuses medical_record
                $docStatuses = $applicant->document_statuses ?? [];
                if (isset($docStatuses['medical_record'])) {
                    unset($docStatuses['medical_record']);
                    $applicant->document_statuses = empty($docStatuses) ? null : $docStatuses;
                }
            }
        });

        static::updated(function ($applicant) {
            // 1. Sync grade_level to associated Student
            if ($applicant->wasChanged('grade_level') && $applicant->student) {
                $student = $applicant->student;
                $student->grade_level = $applicant->grade_level;
                $student->saveQuietly();
            }
            // 2. Sync grade_level to associated StudentAccount (SOA)
            if ($applicant->wasChanged('grade_level') && $applicant->student && $applicant->student->account) {
                $account = $applicant->student->account;
                $account->grade_level = $applicant->grade_level;
                $account->saveQuietly();
            }
            // 3. Sync name changes to student's User account name
            if (($applicant->wasChanged('first_name') || $applicant->wasChanged('middle_name') || $applicant->wasChanged('last_name') || $applicant->wasChanged('suffix')) && $applicant->student && $applicant->student->user) {
                $user = $applicant->student->user;
                $user->name = trim($applicant->first_name . ' ' . ($applicant->middle_name ?? '') . ' ' . $applicant->last_name . ($applicant->suffix ? ' ' . $applicant->suffix : ''));
                $user->saveQuietly();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'family_application_id',
        // Student Info
        'student_type',
        'amis_student_id',
        'learning_mode',
        'timezone',
        'lrn',
        'grade_level',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'gender',
        'date_of_birth',
        'place_of_birth',
        'religion',
        'ethnicity',
        'country',
        'state_province',
        'city',
        'street_address',
        'postal_code',
        'address',
        'email',
        'mobile_country_code',
        'mobile_number',
        // Parent Info
        'father_last_name',
        'father_first_name',
        'father_middle_name',
        'father_occupation',
        'father_email',
        'mother_last_name',
        'mother_first_name',
        'mother_middle_name',
        'mother_occupation',
        'mother_email',
        'home_address',
        'home_state_province',
        'home_city',
        'home_street_address',
        'home_postal_code',
        'parent_country_code',
        'parent_mobile',
        'parent_email',
        'guardian_email',
        'referral_source',
        // Medical & Emergency
        'psych_testing',
        'prescription_med',
        'medical_has_concern',
        'allergies',
        'current_medications',
        'health_conditions',
        'emergency_instructions',
        'medical_history',
        'med_explanation',
        'family_physician',
        'physician_phone',
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',
        // Documents
        'photo_2x2_url',
        'birth_cert_url',
        'report_card_url',
        'marriage_contract_url',
        'medical_record_url',
        'affidavit_url',
        'affidavit_data',
        'document_statuses',
        'review_remarks',
        // Meta
        'school_year',
        'status',
        'last_step',
        'sibling_order',
        'discount_type',
        'discount_percentage',
        'discount_amount',
    ];

    protected $casts = [
        'date_of_birth'      => 'date',
        'last_step'          => 'integer',
        'affidavit_data'     => 'array',
        'document_statuses'  => 'array',
        'sibling_order'      => 'integer',
        'discount_percentage'=> 'decimal:2',
        'discount_amount'    => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class, 'enrollment_applicant_id');
    }

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Student::class, 'enrollment_applicant_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->middle_name ?? '') . ' ' . $this->last_name . ($this->suffix ? ' ' . $this->suffix : ''));
    }

    /**
     * Calculate completion percentage based on filled required fields.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $hasAcademicProof = $this->student_type === 'Old'
            || !empty($this->report_card_url)
            || !empty($this->affidavit_url);

        $checks = [
            // Step 1 (weight: 5 fields)
            !empty($this->student_type),
            !empty($this->grade_level),
            !empty($this->first_name),
            !empty($this->last_name),
            !empty($this->gender),
            !empty($this->date_of_birth),
            !empty($this->place_of_birth),
            !empty($this->religion),
            !empty($this->country),
            !empty($this->street_address),
            !empty($this->mobile_number),
            // Step 2
            !empty($this->parent_mobile),
            // Step 3
            !empty($this->emergency_name),
            !empty($this->emergency_relationship),
            !empty($this->emergency_phone),
            // Step 5 - documents
            !empty($this->photo_2x2_url),
            $hasAcademicProof,
        ];

        $filled = count(array_filter($checks));
        return (int) round(($filled / count($checks)) * 100);
    }

    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value !== null ? mb_strtoupper($value, 'UTF-8') : null;
    }

    public function setMiddleNameAttribute($value)
    {
        $this->attributes['middle_name'] = $value !== null ? mb_strtoupper($value, 'UTF-8') : null;
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value !== null ? mb_strtoupper($value, 'UTF-8') : null;
    }
}
