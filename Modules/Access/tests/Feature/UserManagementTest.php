<?php

namespace Modules\Access\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private const COLUMNS = ['id', 'user', 'phone', 'roles', 'status', 'last_seen', 'actions'];

    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
    }

    public function test_admin_can_list_search_and_create_users(): void
    {
        $this->actingAs($this->admin)->get('/admin/access/users?search=Admin')->assertOk()->assertSee('value="Admin"', false);

        $this->actingAs($this->admin)->post('/admin/access/users', [
            'name' => 'Cashier One',
            'email' => 'cashier1@pos.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'is_active' => '1',
            'roles' => ['Cashier'],
        ])->assertRedirect('/admin/access/users')->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'cashier1@pos.test')->firstOrFail()->hasRole('Cashier'));
    }

    public function test_users_table_searches_by_prefix_and_filters_by_role_and_tab(): void
    {
        $cashier = $this->userWithRole(SystemRole::Cashier, ['name' => 'Zaw Cashier', 'email' => 'zaw@pos.test']);
        $inactive = $this->userWithRole(SystemRole::Cashier, ['name' => 'Zin Off', 'is_active' => false]);
        $this->actingAs($this->admin);
        $rows = fn (array $params = [], ?string $search = null) => $this->dataTable(route('admin.access.users.data'), self::COLUMNS, $params, $search);

        $response = $rows([], 'Zaw')->assertJsonPath('recordsTotal', 3)->assertJsonPath('recordsFiltered', 1);
        $this->assertSame($cashier->id, $response->json('data.0.id'));
        $this->assertSame(self::COLUMNS, array_keys($response->json('data.0')), 'only the listed columns are sent');

        $rows([], 'Cashier')->assertJsonPath('recordsFiltered', 0); // prefix search: no "%Cashier%" match
        $rows(['role' => 'Cashier'])->assertJsonPath('recordsFiltered', 2);
        $rows(['status' => 'inactive'])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $inactive->id);
    }

    public function test_users_table_sorts_only_by_allowed_columns_and_caps_page_size(): void
    {
        $this->actingAs($this->admin);
        $this->userWithRole(SystemRole::Cashier, ['name' => 'Aaron']);
        $columns = array_map(fn ($data) => ['data' => $data, 'name' => $data === 'user' ? 'name' : $data, 'orderable' => 'true'], self::COLUMNS);

        $byName = $this->dataTable(route('admin.access.users.data'), self::COLUMNS, ['columns' => $columns, 'order' => [['column' => 1, 'dir' => 'asc']]]);
        $this->assertStringContainsString('Aaron', $byName->json('data.0.user'));

        // A sort on a column that isn't allowed (a missing column here, which would be a SQL error) is ignored rather than run.
        $columns[2]['name'] = 'no_such_column';
        $this->dataTable(route('admin.access.users.data'), self::COLUMNS, ['columns' => $columns, 'order' => [['column' => 2, 'dir' => 'asc']]])->assertOk();

        // length=-1 would make yajra return every row.
        $this->dataTable(route('admin.access.users.data'), self::COLUMNS, ['length' => -1])->assertUnprocessable();
        $this->dataTable(route('admin.access.users.data'), self::COLUMNS, ['length' => 101])->assertUnprocessable();
    }

    public function test_cashier_cannot_open_user_management(): void
    {
        $cashier = $this->userWithRole(SystemRole::Cashier);

        $this->actingAs($cashier)->get('/admin/access/users')->assertForbidden();
        $this->actingAs($cashier)->dataTable(route('admin.access.users.data'), self::COLUMNS)->assertForbidden();
    }

    public function test_manager_cannot_grant_administrator_role(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->post('/admin/access/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@pos.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'roles' => ['Administrator'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@pos.test']);
    }

    public function test_manager_cannot_edit_an_administrator(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get("/admin/access/users/{$this->admin->id}/edit")->assertForbidden();
        $this->actingAs($manager)->put("/admin/access/users/{$this->admin->id}", [
            'name' => 'Hijacked',
            'email' => $this->admin->email,
            'password' => 'hijacked-pass-1',
            'password_confirmation' => 'hijacked-pass-1',
            'roles' => ['Manager'],
        ])->assertForbidden();
    }

    public function test_user_cannot_delete_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->delete("/admin/access/users/{$this->admin->id}")->assertSessionHasErrors('user');
        $this->actingAs($this->admin)->patch("/admin/access/users/{$this->admin->id}/status")->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_last_active_administrator_cannot_lose_the_role(): void
    {
        $this->actingAs($this->admin)->put("/admin/access/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'is_active' => '1',
            'roles' => ['Manager'],
        ])->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->isAdministrator());
    }

    public function test_admin_can_deactivate_and_delete_another_user(): void
    {
        $cashier = $this->userWithRole(SystemRole::Cashier);

        $this->actingAs($this->admin)->patch("/admin/access/users/{$cashier->id}/status")->assertSessionHasNoErrors();
        $this->assertFalse($cashier->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/access/users/{$cashier->id}")->assertRedirect('/admin/access/users');
        $this->assertSoftDeleted($cashier);
    }
}
