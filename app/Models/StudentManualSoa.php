<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class StudentManualSoa extends Model
{
    use HasFactory;

    protected $table = 'student_manual_soas';

    protected $fillable = [
        'student_identifier',
        'student_name',
        'family_email',
        'grade_level',
        'school_year',
        'billing_month',
        'version',
        'is_current',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by',
        'remarks',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
        'file_size' => 'integer',
    ];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeForStudent(Builder $query, string $identifier): Builder
    {
        return $query->where('student_identifier', $identifier);
    }

    public function scopeForFamily(Builder $query, string $email): Builder
    {
        return $query->where('family_email', $email);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        return $bytes.' B';
    }

    public function getIsPdfAttribute(): bool
    {
        return str_contains(strtolower($this->mime_type), 'pdf') || str_ends_with(strtolower($this->original_filename), '.pdf');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with(strtolower($this->mime_type), 'image/')
            || in_array(strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
    }
}
