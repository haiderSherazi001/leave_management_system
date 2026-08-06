<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Support\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Deliberately does NOT use BelongsToCompany/CompanyScope, unlike every
     * other tenant-owned model. User is the Authenticatable: the session
     * guard resolves the logged-in user by re-querying User on every
     * authenticated request (EloquentUserProvider::retrieveById()). If that
     * query carried a global scope reading Tenant::id() -> Auth::user(), it
     * would recurse infinitely (Auth::user() re-entering its own
     * still-in-progress resolution) - this was hit and confirmed via a real
     * HTTP request during rollout (a PHP memory-exhaustion fatal, not
     * caught by tests since actingAs() injects the user directly and never
     * exercises retrieveById()). Every listing/lookup of users elsewhere in
     * the app scopes company_id explicitly by hand instead - see
     * EmployeeDirectoryService, AttendanceExportService, LeaveRequestService.
     * Only the creating-time auto-stamp is safe to keep (fires on insert,
     * never during auth resolution).
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->company_id) && Tenant::id() !== null) {
                $model->company_id = Tenant::id();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'department_id',
        'manager_id',
        'joined_at',
        'company_id',
        'welcomed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'joined_at' => 'date',
            'welcomed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isHr(): bool
    {
        return $this->role === UserRole::Hr;
    }

    /**
     * Where "Dashboard" takes this user — HR lands on the real HR
     * dashboard rather than the generic placeholder, since it's the
     * page they actually need after logging in.
     */
    public function homeRouteName(): string
    {
        return $this->isHr() ? 'admin.dashboard' : 'dashboard';
    }
}
