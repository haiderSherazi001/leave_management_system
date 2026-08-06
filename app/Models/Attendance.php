<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'check_in_at',
        'check_out_at',
        'status',
        'notes',
        'company_id',
    ];

    /**
     * No 'date' cast, deliberately - same reasoning as Holiday's date column:
     * casting to 'date' serializes back to the DB using getDateFormat()
     * (a full "Y-m-d H:i:s" datetime string on this connection), which a
     * strict-affinity DB like MySQL truncates to a bare date but SQLite
     * (used in tests) stores verbatim - making a row's own date compare as
     * "greater than" a plain "Y-m-d" whereBetween() upper bound that
     * equals it, silently dropping it from the range. The app itself never
     * writes attendance through Eloquent (AttendanceService uses DB::table()
     * with a plain toDateString()), so nothing needs the cast.
     */
    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
