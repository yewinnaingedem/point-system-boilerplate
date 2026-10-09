<?php

namespace Modules\Customer\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

/** The customer picker's search: name, email, phone or customer id; Select2 format; paged. */
class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_customers_by_name_email_phone_or_customer_id(): void
    {
        $this->seedAccess();
        $aung = Customer::factory()->create(['name' => 'Aung Aung', 'email' => 'aung@example.com', 'phone' => '0991234567', 'external_id' => 'shop-7']);
        Customer::factory()->create(['name' => 'Mya Mya', 'email' => 'mya@example.com', 'phone' => '0977000000']);
        app(TierQualificationEngine::class)->enrol($aung->id);
        app(PointWallet::class)->credit($aung->id, 1250, PointTransactionType::Earn);
        $this->actingAs($this->userWithRole(SystemRole::Administrator));

        foreach (['Aung', 'aung@', '09912', 'shop-7'] as $term) {
            $this->getJson(route('admin.customers.search', ['q' => $term]))->assertOk()
                ->assertJsonCount(1, 'results')
                ->assertJsonPath('results.0.id', $aung->id)
                ->assertJsonPath('results.0.text', 'Aung Aung')
                ->assertJsonPath('results.0.points', 1250)
                ->assertJsonPath('results.0.tier', 'Silver')
                ->assertJsonPath('results.0.detail', 'aung@example.com · 0991234567 · #shop-7');
        }
        $this->getJson(route('admin.customers.search', ['q' => 'nobody']))->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_pages_of_twenty(): void
    {
        $this->seedAccess();
        Customer::factory()->count(25)->create(['name' => 'Same Name']);
        $this->actingAs($this->userWithRole(SystemRole::Administrator));

        $this->getJson(route('admin.customers.search', ['q' => 'Same']))->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
        $this->getJson(route('admin.customers.search', ['q' => 'Same', 'page' => 2]))->assertJsonCount(5, 'results')->assertJsonPath('pagination.more', false);
    }

    public function test_only_staff_who_pick_customers_can_search(): void
    {
        $this->seedAccess();
        $this->actingAs($this->userWithRole(SystemRole::Cashier))->getJson(route('admin.customers.search', ['q' => 'a']))->assertForbidden();
    }
}
