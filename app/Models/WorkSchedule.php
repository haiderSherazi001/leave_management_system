<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $table = 'work_schedule';

    protected $fillable = [
        'working_days',
        'start_time',
        'end_time',
        'grace_minutes',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'grace_minutes' => 'integer',
        ];
    }
}
