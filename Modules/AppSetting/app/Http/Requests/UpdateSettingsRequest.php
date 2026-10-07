<?php

namespace Modules\AppSetting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\AppSetting\Support\SettingGroup;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit-appsetting') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->settingGroup()->validationRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->settingGroup()->fields)
            ->mapWithKeys(fn ($field) => [$field->key => strtolower($field->label)])
            ->all();
    }

    public function settingGroup(): SettingGroup
    {
        return app(SettingCatalog::class)->group((string) $this->route('group')) ?? abort(404);
    }
}
