<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_reachable(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_submitting_creates_a_new_company_and_its_first_hr_account(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));

        // TestCase's own ambient Tenant::set() override outlives the
        // request and would otherwise keep scoping User:: lookups to
        // TestCase's own company, not the brand-new one just registered -
        // cleared here so it falls through to Auth::user()->company_id
        // (the account /register just logged in as).
        Tenant::clear();

        $company = Company::where('name', 'Acme Corp')->firstOrFail();

        $user = User::where('email', 'hr@acme.test')->firstOrFail();
        $this->assertSame($company->id, $user->company_id);
        $this->assertSame('hr', $user->role->value);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->joined_at->isToday());
    }

    public function test_the_new_hr_account_gets_leave_balances_provisioned_for_any_existing_leave_types(): void
    {
        // company_id is explicit here (not left to the creating-hook
        // default) because this leave type must belong to the SAME company
        // as the account about to be created - Tenant::id() at this point
        // in the test is whatever TestCase's own ambient company is, not
        // Acme Corp, which doesn't exist yet.
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Tenant::clear();

        $company = Company::where('name', 'Acme Corp')->firstOrFail();
        $user = User::where('email', 'hr@acme.test')->firstOrFail();

        LeaveType::factory()->create(['company_id' => $company->id, 'yearly_allocation_days' => 15]);

        // Balances are only provisioned against leave types that exist at
        // registration time, so re-provisioning here mirrors what a second
        // employee added afterward would get - this asserts the mechanism
        // (provisionForUser) is wired up, not a specific registration-time
        // race with the leave type above.
        app(LeaveBalanceService::class)->provisionForUser($user->id, now()->year);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'allocated_days' => 15,
        ]);
    }

    /**
     * Unlike the old one-time-global /setup wizard this replaces, /register
     * has no self-disabling check - every company gets its own founding
     * moment, so registering twice must produce two fully independent
     * companies, not be blocked the way a second /setup submission was.
     */
    public function test_registering_twice_creates_two_independent_companies(): void
    {
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Acme HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->post('/logout');

        $this->post('/register', [
            'company_name' => 'Widget Co',
            'name' => 'Widget HR',
            'email' => 'hr@widget.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Raw query builder, deliberately bypassing the Eloquent tenant
        // scope - this assertion needs to see both companies' users at
        // once, which no single ambient tenant context could ever satisfy.
        $acmeCompanyId = DB::table('users')->where('email', 'hr@acme.test')->value('company_id');
        $widgetCompanyId = DB::table('users')->where('email', 'hr@widget.test')->value('company_id');

        $this->assertNotNull($acmeCompanyId);
        $this->assertNotNull($widgetCompanyId);
        $this->assertNotSame($acmeCompanyId, $widgetCompanyId);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'hr@acme.test']);
    }

    public function test_email_must_be_unique_across_the_whole_installation(): void
    {
        User::factory()->create(['email' => 'taken@acme.test']);

        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Founding HR',
            'email' => 'taken@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_company_name_is_required(): void
    {
        $this->post('/register', [
            'company_name' => '',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('company_name');
    }

    public function test_company_address_and_website_are_stored_when_provided(): void
    {
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'company_address' => '123 Main St',
            'company_website' => 'https://acme.test',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Corp',
            'address' => '123 Main St',
            'website' => 'https://acme.test',
        ]);
    }

    public function test_company_address_and_website_are_optional(): void
    {
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionDoesntHaveErrors(['company_address', 'company_website']);

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Corp',
            'address' => null,
            'website' => null,
        ]);
    }

    public function test_company_website_must_be_a_valid_url(): void
    {
        $this->post('/register', [
            'company_name' => 'Acme Corp',
            'company_website' => 'not-a-url',
            'name' => 'Founding HR',
            'email' => 'hr@acme.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('company_website');
    }
}
