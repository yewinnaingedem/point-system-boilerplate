<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Modules\Api\Http\Resources\SettingsResource;
use Modules\AppSetting\Services\SettingService;

class SettingController extends Controller
{
    public function __invoke(SettingService $settings): SettingsResource
    {
        return new SettingsResource($settings);
    }
}
