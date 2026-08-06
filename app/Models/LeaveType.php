<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
        'code',
        'yearly_allocation_days',
        'carry_forward_enabled',
        'carry_forward_max_days',
        'description',
        'is_active',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'yearly_allocation_days' => 'integer',
            'carry_forward_enabled' => 'boolean',
            'carry_forward_max_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
