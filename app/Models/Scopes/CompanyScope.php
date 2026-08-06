<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters every query to the current tenant. Deliberately a no-op when
 * Tenant::id() is null rather than filtering to an impossible match — that's
 * what keeps login/password-reset (which must find a User by email before
 * any company is known) working unchanged, since Auth::attempt() and the
 * `unique:` validation rule both resolve via raw connection queries, not
 * through this scope, anyway. See App\Support\Tenant for why this can be
 * dangerous in a console context with no session and no explicit
 * Tenant::set() — those call sites are handled directly, not by this scope.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = Tenant::id();

        if ($companyId !== null) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
