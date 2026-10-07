<?php

namespace Modules\AppSetting\Support;

/**
 * Definition of one editable setting: how it is rendered, validated and defaulted.
 */
final class SettingField
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const NUMBER = 'number';

    public const EMAIL = 'email';

    public const SELECT = 'select';

    public const COLOR = 'color';

    public const IMAGE = 'image';

    /**
     * @param  list<mixed>  $rules  validation rules (without required/nullable; see $required)
     * @param  array<string, string>  $options  value => label, for SELECT
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TEXT,
        public readonly mixed $default = null,
        public readonly array $rules = [],
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly ?string $help = null,
        public readonly bool $wide = false,
    ) {}

    public function isFile(): bool
    {
        return $this->type === self::IMAGE;
    }

    /**
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        // An image field left empty keeps the current file, so it is never required on update.
        $presence = $this->required && ! $this->isFile() ? 'required' : 'nullable';

        return [$presence, ...$this->rules];
    }
}
