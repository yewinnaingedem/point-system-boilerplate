<?php

namespace Tests\Feature\Database;

use App\Models\User;
use App\Services\Database\SqliteDataCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class SqliteDataCopierTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        // A separate SQLite file with the app schema and some data, playing the "old" database.
        $this->file = storage_path('framework/testing/source-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->file));
        touch($this->file);
        config(['database.connections.copier_source' => ['driver' => 'sqlite', 'database' => $this->file, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'copier_source', '--force' => true]);

        DB::connection('copier_source')->table('users')->insert([
            ['id' => 7, 'name' => 'Old Admin', 'email' => 'old@pos.test', 'password' => 'hash-1', 'is_active' => 1],
            ['id' => 9, 'name' => 'Old Cashier', 'email' => 'cashier@pos.test', 'password' => 'hash-2', 'is_active' => 0],
        ]);
        DB::connection('copier_source')->table('sessions')->insert(['id' => 's1', 'user_id' => 7, 'payload' => '', 'last_activity' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('copier_source');
        File::delete($this->file);
        parent::tearDown();
    }

    public function test_rows_are_copied_with_their_ids_and_the_target_is_replaced(): void
    {
        User::factory()->create(['email' => 'will-be-replaced@pos.test']);

        $copied = app(SqliteDataCopier::class)->copy($this->file, config('database.default'));

        $this->assertSame(2, $copied['users']);
        $this->assertArrayNotHasKey('sessions', $copied);
        $this->assertArrayNotHasKey('migrations', $copied);
        $this->assertSame(['old@pos.test', 'cashier@pos.test'], User::orderBy('id')->pluck('email')->all());
        $this->assertSame([7, 9], User::orderBy('id')->pluck('id')->all());
    }

    public function test_command_needs_confirmation_or_force(): void
    {
        $this->artisan('db:import-sqlite', ['path' => $this->file])
            ->expectsConfirmation('Every copied table in the target is emptied and replaced. Continue?', 'no')
            ->assertFailed();

        $this->artisan('db:import-sqlite', ['path' => $this->file, '--force' => true])->assertSuccessful();
        $this->assertSame(2, User::count());
    }

    public function test_missing_file_is_reported(): void
    {
        $this->expectException(RuntimeException::class);

        app(SqliteDataCopier::class)->copy('/nope/missing.sqlite', config('database.default'));
    }
}
