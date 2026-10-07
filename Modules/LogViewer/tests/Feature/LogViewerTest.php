<?php

namespace Modules\LogViewer\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\LogViewer\Services\LogFileRepository;
use Modules\LogViewer\Services\LogLevelCounter;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();

        $this->dir = storage_path('framework/testing/logs-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        config(['logviewer.path' => $this->dir]);

        File::put("{$this->dir}/laravel-2026-10-07.log", implode("\n", [
            '[2026-10-07 08:00:00] testing.INFO: Shift opened',
            '[2026-10-07 08:05:00] testing.ERROR: Payment gateway timeout',
            '#0 /app/Gateway.php(40): charge()',
            '[2026-10-07 08:10:00] testing.ERROR: Printer offline',
        ]));
        File::put("{$this->dir}/laravel-2026-10-06.log", "[2026-10-06 21:00:00] testing.WARNING: Low stock\n");
        File::put("{$this->dir}/laravel.log", "[2026-10-01 08:00:00] testing.INFO: old single-channel file\n");
        File::put("{$this->dir}/secret.txt", 'not a log');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_admin_sees_files_and_entries_with_level_counts(): void
    {
        $this->actingAs($this->admin)->get('/admin/logs')
            ->assertOk()
            ->assertSeeInOrder(['07/10/2026', '06/10/2026'])
            ->assertSee(route('admin.logs.show', ['file' => 'laravel-2026-10-07.log', 'level' => 'error']))
            ->assertDontSee('secret.txt')
            ->assertDontSee(route('admin.logs.show', 'laravel.log'));

        $this->actingAs($this->admin)->get('/admin/logs/laravel-2026-10-07.log')
            ->assertOk()
            ->assertSeeInOrder(['Printer offline', 'Payment gateway timeout', 'Shift opened'])
            ->assertSee('#0 /app/Gateway.php(40): charge()', false);
    }

    public function test_level_and_search_filters(): void
    {
        $this->actingAs($this->admin)->get('/admin/logs/laravel-2026-10-07.log?level=error')
            ->assertSee('Printer offline')->assertDontSee('Shift opened');

        $this->actingAs($this->admin)->get('/admin/logs/laravel-2026-10-07.log?search=gateway')
            ->assertSee('Payment gateway timeout')->assertDontSee('Printer offline');
    }

    public function test_only_log_files_in_the_folder_can_be_opened(): void
    {
        $this->actingAs($this->admin)->get('/admin/logs/secret.txt')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/logs/laravel.log')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/logs/..%2F..%2F.env')->assertNotFound();
    }

    public function test_large_file_shows_only_the_newest_part(): void
    {
        config(['logviewer.max_bytes' => 60]);

        $this->actingAs($this->admin)->get('/admin/logs/laravel-2026-10-07.log')
            ->assertOk()->assertSee('Only the newest')->assertSee('Printer offline')->assertDontSee('Shift opened');
    }

    public function test_level_counts_cover_the_whole_file_and_refresh_when_it_grows(): void
    {
        $counter = app(LogLevelCounter::class);
        $repo = app(LogFileRepository::class);

        $counts = $counter->count($repo->find('laravel-2026-10-07.log'));
        $this->assertSame(['error' => 2, 'info' => 1], array_filter(array_intersect_key($counts, ['error' => 0, 'info' => 0])));

        File::append("{$this->dir}/laravel-2026-10-07.log", "\n[2026-10-07 09:00:00] testing.ERROR: Again\n");
        clearstatcache();

        $this->assertSame(3, $counter->count($repo->find('laravel-2026-10-07.log'))['error']);
    }

    public function test_download_and_delete(): void
    {
        $this->actingAs($this->admin)->get('/admin/logs/laravel-2026-10-07.log/download')->assertOk()->assertDownload('laravel-2026-10-07.log');

        $this->actingAs($this->admin)->delete('/admin/logs/laravel-2026-10-07.log')->assertRedirect('/admin/logs');
        $this->assertFileDoesNotExist("{$this->dir}/laravel-2026-10-07.log");
    }

    public function test_manager_cannot_read_logs(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get('/admin/logs')->assertForbidden();
        $this->actingAs($manager)->delete('/admin/logs/laravel-2026-10-07.log')->assertForbidden();
        $this->assertFileExists("{$this->dir}/laravel-2026-10-07.log");
    }
}
