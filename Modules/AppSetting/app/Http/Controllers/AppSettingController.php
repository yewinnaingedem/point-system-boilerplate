<?php

namespace Modules\AppSetting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\AppSetting\Http\Requests\UpdateSettingsRequest;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;

class AppSettingController extends Controller
{
    private const DEFAULT_GROUP = 'general';

    public function __construct(
        private readonly SettingCatalog $catalog,
        private readonly SettingService $settings,
    ) {}

    public function edit(string $group = self::DEFAULT_GROUP): View
    {
        $current = $this->catalog->group($group) ?? abort(404);

        return view('appsetting::edit', [
            'groups' => $this->catalog->groups(),
            'current' => $current,
            'values' => $this->settings->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->settings->saveGroup($request->settingGroup(), $request->validated());

        return redirect()
            ->route('admin.settings.edit', $group)
            ->with('success', __(':group settings saved.', ['group' => $request->settingGroup()->label]));
    }
}
