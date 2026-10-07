<?php

namespace Modules\AppSetting\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
    }

    public function test_defaults_apply_before_anything_is_saved(): void
    {
        $this->assertSame('POS System', setting('app_name'));
        $this->assertSame('1,500 Ks', money(1500));
    }

    public function test_admin_can_save_general_settings_with_a_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put('/admin/settings/general', [
            'app_name' => 'Golden POS',
            'company_name' => 'Golden Mart',
            'company_email' => 'hello@golden.test',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect('/admin/settings/general')->assertSessionHasNoErrors();

        $this->assertSame('Golden POS', setting('app_name'));
        Storage::disk('public')->assertExists(setting('logo'));

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertSee('Golden Mart');
    }

    public function test_invalid_color_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/settings/appearance', ['primary_color' => 'red;}</style><script>'])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_unknown_group_is_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/nope')->assertNotFound();
    }

    public function test_manager_can_view_but_not_change_settings(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get('/admin/settings')->assertOk();
        $this->actingAs($manager)->put('/admin/settings/pos', ['currency_code' => 'USD'])->assertForbidden();
    }
}
