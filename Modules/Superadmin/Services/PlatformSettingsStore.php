<?php

namespace Modules\Superadmin\Services;

use App\Contracts\PlatformSettings;
use Modules\Superadmin\Models\Setting;

/**
 * App\Contracts\PlatformSettings sürücüsü: superadmin_settings (Setting modeli, önbellekli).
 */
class PlatformSettingsStore implements PlatformSettings
{
    /** "Yeni kayıt açık" hiç kaydedilmediyse geçerli değer (SettingsController varsayılanı da bu). */
    public const ALLOW_SIGNUP_DEFAULT = true;

    public function group(string $group): array
    {
        return Setting::getGroup($group);
    }

    public function putGroup(string $group, array $values): void
    {
        Setting::setGroup($group, $values);
    }

    public function allowsSignup(): bool
    {
        return (bool) (Setting::allGrouped()['general']['allowSignup'] ?? self::ALLOW_SIGNUP_DEFAULT);
    }
}
