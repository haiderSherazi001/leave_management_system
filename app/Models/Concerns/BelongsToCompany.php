<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\CompanyScope;
use App\Support\Tenant;

trait BelongsToCompany
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model): void {
            if (blank($model->company_id) && Tenant::id() !== null) {
                $model->company_id = Tenant::id();
            }
        });
    }
}
