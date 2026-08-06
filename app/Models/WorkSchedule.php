<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'work_schedule';

    protected $fillable = [
        'working_days',
        'start_time',
        'end_time',
        'grace_minutes',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'grace_minutes' => 'integer',
        ];
    }
}
