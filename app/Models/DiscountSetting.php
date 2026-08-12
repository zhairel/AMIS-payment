<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountSetting extends Model
{
    protected $fillable = [
        'per_child_percentage',
        'first_child_percentage',
        'second_child_percentage',
        'third_child_percentage',
        'fourth_child_percentage',
        'is_active',
    ];

    protected $casts = [
        'per_child_percentage' => 'integer',
        'first_child_percentage' => 'integer',
        'second_child_percentage' => 'integer',
        'third_child_percentage' => 'integer',
        'fourth_child_percentage' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function current(): self
    {
        return static::where('is_active', true)->latest()->first()
            ?? static::query()->create();
    }

    public function siblingPercentageForOrder(int $siblingOrder): float
    {
        return $this->siblingPercentageForFamilySize($siblingOrder);
    }

    public function siblingPercentageForFamilySize(int $childCount): float
    {
        if (!$this->is_active || $childCount < 1) {
            return 0.0;
        }

        return min(100.0, $childCount * (float) $this->per_child_percentage);
    }
}
