<?php

namespace Modules\AppSetting\Support;

/**
 * A tab on the settings page.
 */
final class SettingGroup
{
    /**
     * @param  list<SettingField>  $fields
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $icon,
        public readonly array $fields,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function validationRules(): array
    {
        return collect($this->fields)
            ->mapWithKeys(fn (SettingField $field) => [$field->key => $field->validationRules()])
            ->all();
    }
}
