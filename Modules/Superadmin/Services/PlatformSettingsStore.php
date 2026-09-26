<?php

namespace Modules\Superadmin\Services;

use App\Contracts\PlatformSettings;
use Modules\Superadmin\Models\Setting;

/**
 * App\Contracts\PlatformSettings sürücüsü: superadmin_settings (Setting modeli, önbellekli).
 */
class PlatformSettingsStore implements PlatformSettings
{
    public function group(string $group): array
    {
        return Setting::getGroup($group);
    }

    public function putGroup(string $group, array $values): void
    {
        Setting::setGroup($group, $values);
    }
}
