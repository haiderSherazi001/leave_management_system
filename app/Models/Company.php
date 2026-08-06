<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The tenant root. Every other tenant-owned table hangs off this via a
 * company_id column (see App\Models\Concerns\BelongsToCompany) — this model
 * itself carries no company_id, since it IS the tenant.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'website',
        'dismissed_setup_alerts',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_setup_alerts' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function dismissedSetupAlertKeys(): array
    {
        return $this->dismissed_setup_alerts ?? [];
    }
}
