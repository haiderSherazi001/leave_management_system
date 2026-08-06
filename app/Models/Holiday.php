<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'date',
        'name',
        'company_id',
    ];

    /**
     * No 'date' cast on purpose: this app never writes holidays via Eloquent
     * (HolidayService uses the query builder exclusively, storing plain
     * Y-m-d strings) — casting here would make Holiday::factory() serialize
     * a full datetime string that silently fails to match those lookups.
     */
}
