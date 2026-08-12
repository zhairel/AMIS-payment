<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\AmisVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'role',
        'account_status',
        'email_verified_at',
        'last_active_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function enrollmentApplicant(): HasOne
    {
        return $this->hasOne(EnrollmentApplicant::class);
    }

    public function enrollmentApplicants(): HasMany
    {
        return $this->hasMany(EnrollmentApplicant::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            EnrollmentApplicant::class,
            'user_id', // Foreign key on EnrollmentApplicant table (parent user_id)
            'enrollment_applicant_id', // Foreign key on Student table
            'id', // Local key on parent User table
            'id' // Local key on EnrollmentApplicant table
        );
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function receiptScanLogs(): HasMany
    {
        return $this->hasMany(ReceiptScanLog::class);
    }

    public function receiptSubmissions(): HasMany
    {
        return $this->hasMany(ReceiptSubmission::class);
    }

    public function familyAdvanceCredits(): HasMany
    {
        return $this->hasMany(FamilyAdvanceCredit::class);
    }

    public function isVerified(): bool
    {
        return $this->account_status === 'verified';
    }

    public function isApplicant(): bool
    {
        return $this->role === 'applicant';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new AmisVerifyEmail);
    }

    public static function makeUniqueUsername(string $email): string
    {
        $base = Str::of($email)
            ->before('@')
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '.')
            ->trim('.-_')
            ->limit(40, '')
            ->value();

        $base = $base !== '' ? $base : 'applicant';
        $username = $base;
        $suffix = 2;

        while (self::where('username', $username)->exists()) {
            $username = Str::limit($base, 35, '').'-'.$suffix;
            $suffix++;
        }

        return $username;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value !== null ? mb_strtoupper($value, 'UTF-8') : null;
    }

    public function isActive(): bool
    {
        return $this->last_active_at && $this->last_active_at->gt(now()->subMinutes(5));
    }
}
