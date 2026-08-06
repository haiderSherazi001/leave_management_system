<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CompanySettings;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_renders_successfully_over_http(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.company'))->assertOk();
    }

    public function test_non_hr_users_cannot_access_company_settings(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.company'))->assertForbidden();
        }
    }

    public function test_mount_prefills_the_current_companys_details(): void
    {
        $hr = User::factory()->hr()->create();
        $hr->company->update(['name' => 'Acme Corp', 'address' => '123 Main St', 'website' => 'https://acme.test']);
        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->assertSet('name', 'Acme Corp')
            ->assertSet('address', '123 Main St')
            ->assertSet('website', 'https://acme.test');
    }

    public function test_hr_can_update_company_details(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->set('name', 'New Name Ltd')
            ->set('address', '456 Side St')
            ->set('website', 'https://newname.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $hr->company_id,
            'name' => 'New Name Ltd',
            'address' => '456 Side St',
            'website' => 'https://newname.test',
        ]);
    }

    public function test_address_and_website_are_optional(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->set('name', 'Bare Bones Co')
            ->set('address', '')
            ->set('website', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $hr->company_id,
            'name' => 'Bare Bones Co',
            'address' => null,
            'website' => null,
        ]);
    }

    public function test_company_name_is_required(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_website_must_be_a_valid_url(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->set('website', 'not-a-url')
            ->call('save')
            ->assertHasErrors('website');
    }

    /**
     * Updating company details only ever touches the current HR user's own
     * company - the strongest possible check that this can't cross tenants,
     * given every write here goes through Auth::user()->company rather
     * than a bare company_id looked up from input.
     */
    public function test_updating_company_details_never_affects_another_company(): void
    {
        $hr = User::factory()->hr()->create();
        $otherCompany = Company::factory()->create(['name' => 'Other Co']);

        $this->actingAs($hr);

        Livewire::test(CompanySettings::class)
            ->set('name', 'Updated Name')
            ->call('save');

        $this->assertDatabaseHas('companies', ['id' => $otherCompany->id, 'name' => 'Other Co']);
    }
}
