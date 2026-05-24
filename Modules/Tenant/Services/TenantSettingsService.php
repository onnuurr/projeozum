<?php

namespace Modules\Tenant\Services;

use Illuminate\Validation\ValidationException;
use Modules\Tenant\Models\Tenant;

class TenantSettingsService
{
    private const ALLOWED_KEYS = [
        'currency',
        'language',
        'notification_email',
        'order_auto_confirm',
        'invoice_prefix',
        'shipping_address_default',
    ];

    private const DEFAULTS = [
        'currency'                 => 'TRY',
        'language'                 => 'tr',
        'notification_email'       => '',
        'order_auto_confirm'       => false,
        'invoice_prefix'           => 'INV',
        'shipping_address_default' => [],
    ];

    public function get(Tenant $tenant): array
    {
        return array_merge(self::DEFAULTS, $tenant->settings ?? []);
    }

    public function update(Tenant $tenant, array $data): array
    {
        $unknown = array_diff(array_keys($data), self::ALLOWED_KEYS);
        if (! empty($unknown)) {
            throw ValidationException::withMessages([
                'settings' => 'Bilinmeyen ayar anahtarları: ' . implode(', ', $unknown),
            ]);
        }

        $merged = array_merge($tenant->settings ?? [], $data);
        $tenant->update(['settings' => $merged]);

        return $this->get($tenant->fresh());
    }
}
