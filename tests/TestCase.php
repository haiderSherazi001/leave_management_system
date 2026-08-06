<?php

namespace Tests;

use App\Models\Company;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets one ambient company auto-created and pinned via
     * Tenant::set() (after parent::setUp(), so RefreshDatabase's migrations
     * have already run). Every factory's company_id defaults to
     * Tenant::id(), so every existing test's User::factory()/etc. calls
     * keep sharing one implicit tenant unchanged - no per-test-file edits
     * needed. Tests that specifically need more than one company (e.g.
     * TenantIsolationTest) override this with their own Tenant::set() calls.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tenant::set(Company::factory()->create()->id);
    }

    protected function tearDown(): void
    {
        Tenant::clear();

        parent::tearDown();
    }
}
