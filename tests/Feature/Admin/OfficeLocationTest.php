<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OfficeLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_renders_successfully_over_http(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.office-location'))->assertOk();
    }

    public function test_non_hr_users_cannot_access_office_location(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.office-location'))->assertForbidden();
        }
    }

    public function test_mount_pre_fills_from_the_env_default_when_nothing_is_configured_yet(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->assertSet('latitude', (float) config('attendance.office_latitude'))
            ->assertSet('longitude', (float) config('attendance.office_longitude'))
            ->assertSet('radiusMeters', (int) config('attendance.max_checkin_distance_meters'));
    }

    public function test_hr_can_save_a_new_office_location(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->set('latitude', 31.5204)
            ->set('longitude', 74.3587)
            ->set('radiusMeters', 150)
            ->set('label', 'Head Office')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('office_location', [
            'latitude' => 31.5204,
            'longitude' => 74.3587,
            'radius_meters' => 150,
            'label' => 'Head Office',
        ]);
    }

    public function test_saving_twice_updates_the_same_row_rather_than_inserting_a_second_one(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->set('latitude', 31.5204)
            ->set('longitude', 74.3587)
            ->set('radiusMeters', 150)
            ->call('save');

        Livewire::test(OfficeLocation::class)
            ->set('latitude', 31.4)
            ->set('longitude', 74.4)
            ->set('radiusMeters', 200)
            ->call('save');

        $this->assertDatabaseCount('office_location', 1);
        $this->assertDatabaseHas('office_location', ['latitude' => 31.4, 'longitude' => 74.4, 'radius_meters' => 200]);
    }

    public function test_setting_coordinates_updates_the_draft_without_saving(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->call('setCoordinates', 31.5204, 74.3587)
            ->assertSet('latitude', 31.5204)
            ->assertSet('longitude', 74.3587);

        $this->assertDatabaseCount('office_location', 0);
    }

    public function test_validation_rejects_an_out_of_range_latitude(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->set('latitude', 91)
            ->call('save')
            ->assertHasErrors('latitude');
    }

    public function test_validation_rejects_an_out_of_range_longitude(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->set('longitude', -181)
            ->call('save')
            ->assertHasErrors('longitude');
    }

    public function test_validation_rejects_a_radius_that_is_too_small_or_too_large(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(OfficeLocation::class)
            ->set('radiusMeters', 5)
            ->call('save')
            ->assertHasErrors('radiusMeters');

        Livewire::test(OfficeLocation::class)
            ->set('radiusMeters', 10000)
            ->call('save')
            ->assertHasErrors('radiusMeters');
    }
}
