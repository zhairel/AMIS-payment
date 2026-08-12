<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'contact_number',
        'fb_or_whatsapp',
        'student_full_name',
        'grade_level',
        'amis_id',
        'concern_type',
        'subject',
        'description',
        'screenshot_path',
        'status',
    ];

    /**
     * Get the dynamic ticket reference number.
     */
    public function getReferenceNumberAttribute()
    {
        $date = $this->created_at ? $this->created_at->format('Ymd') : date('Ymd');

        return 'AMIS-'.$date.'-'.str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }
}
