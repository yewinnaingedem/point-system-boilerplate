<?php

namespace Modules\AppSetting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stored setting value. Read settings through SettingService (or `setting()`),
 * never with this model directly: the service caches and applies defaults.
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];
}
