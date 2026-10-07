<?php

namespace Modules\AppSetting\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Modules\AppSetting\Models\Setting;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\AppSetting\Support\SettingField;
use Modules\AppSetting\Support\SettingGroup;

/**
 * Reads and writes application settings.
 *
 * Settings live in the `settings` table (not in .env, so saving never rewrites files or
 * clears the config cache). All values are cached together and the cache is dropped on save.
 */
final class SettingService
{
    private const CACHE_KEY = 'appsetting.values';

    private const UPLOAD_DIRECTORY = 'settings';

    /**
     * Copy for the current request, so one page doesn't hit the cache per call.
     * The service is bound `scoped`, so queue jobs and Octane requests start fresh.
     *
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    public function __construct(
        private readonly SettingCatalog $catalog,
        private readonly Cache $cache,
        private readonly ConnectionInterface $db,
        private readonly Filesystem $disk,
        private readonly string $diskName,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Stored values over catalog defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= [...$this->catalog->defaults(), ...$this->stored()];
    }

    /**
     * Public URL of an image setting, or null when none is uploaded.
     */
    public function url(string $key): ?string
    {
        $path = $this->get($key);

        return $path ? $this->disk->url($path) : null;
    }

    /**
     * Save one tab. Image fields left empty keep their current file.
     *
     * @param  array<string, mixed>  $input  validated input for this group
     */
    public function saveGroup(SettingGroup $group, array $input): void
    {
        $replacedFiles = [];

        $this->db->transaction(function () use ($group, $input, &$replacedFiles) {
            foreach ($group->fields as $field) {
                $value = $input[$field->key] ?? null;

                if ($field->isFile()) {
                    if (! $value instanceof UploadedFile) {
                        continue;
                    }
                    $replacedFiles[] = $this->get($field->key);
                    $value = $value->store(self::UPLOAD_DIRECTORY, ['disk' => $this->diskName]);
                }

                Setting::updateOrCreate(
                    ['key' => $field->key],
                    ['group' => $group->key, 'value' => $this->normalize($field, $value)],
                );
            }
        });

        // Old files are removed only after the new paths are committed.
        $this->disk->delete(array_values(array_filter($replacedFiles)));
        $this->forget();
    }

    public function forget(): void
    {
        $this->values = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            return $this->cache->rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (QueryException) {
            // The settings table does not exist yet (fresh install, before migrate).
            return [];
        }
    }

    private function normalize(SettingField $field, mixed $value): ?string
    {
        // An optional field saved empty stays empty; it does not fall back to the default.
        if ($value === null || $value === '') {
            return null;
        }

        return $field->type === SettingField::COLOR ? strtolower((string) $value) : (string) $value;
    }
}
