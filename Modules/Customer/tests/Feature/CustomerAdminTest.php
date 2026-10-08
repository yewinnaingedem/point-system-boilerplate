<?php

namespace Modules\Customer\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_customers_and_sees_their_tier_history(): void
    {
        $this->seedAccess();
        $engine = app(TierQualificationEngine::class);
        $gold = Customer::factory()->create(['name' => 'Kyaw Gold']);
        $engine->enrol($gold->id);
        $engine->processTransaction($gold->id, 600000, now());
        $silver = Customer::factory()->create(['name' => 'Su Silver']);
        $engine->enrol($silver->id);

        $this->actingAs($this->userWithRole(SystemRole::Administrator));
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.customers.show', $gold))->assertOk()->assertSee('Kyaw Gold')->assertSee('more this cycle to reach Platinum');

        $columns = ['id', 'customer', 'phone', 'tier', 'points', 'last_login', 'status', 'actions'];
        $this->dataTable(route('admin.customers.data'), $columns, ['tier' => 'gold'])
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $gold->id);
        $this->dataTable(route('admin.customers.data'), $columns, [], 'Su')->assertJsonPath('data.0.id', $silver->id);

        $history = $this->dataTable(route('admin.customers.tier-history', $gold), ['date', 'transition', 'from', 'to', 'cycle_spent', 'guarantee'],
            ['columns' => [['data' => 'date', 'name' => 'occurred_at', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'desc']]])->json('data');
        $this->assertStringContainsString('Upgraded', $history[0]['transition']);
        $this->assertStringContainsString('Enrolled', $history[1]['transition']);
    }

    public function test_deactivating_signs_the_customer_out_everywhere(): void
    {
        $this->seedAccess();
        $customer = Customer::factory()->create();
        $customer->createToken('phone', ['customer']);

        $this->actingAs($this->userWithRole(SystemRole::Administrator))
            ->patch(route('admin.customers.status', $customer))->assertSessionHas('success');

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        $this->seedAccess();
        $customer = Customer::factory()->create();
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.customers.index'))->assertForbidden();
        $this->get(route('admin.customers.show', $customer))->assertForbidden();
        $this->patch(route('admin.customers.status', $customer))->assertForbidden();
    }
}
