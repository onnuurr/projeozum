<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Models\Setting;
use Modules\Superadmin\Services\SystemInfoService;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    /**
     * Settings.vue içindeki sekme/grup anahtarları.
     */
    public const GROUPS = [
        'general',
        'security',
        'mail',
        'notifications',
        'billing',
        'storage',
        'api',
        'performance',
    ];

    /**
     * Hassas alanların UI'a maskelenmiş hâli. Kullanıcı bu değeri
     * değiştirmeden geri POST ederse mevcut şifreli değer korunur.
     */
    public const MASK = '••••••••';

    private array $roleColors = [
        '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2',
        '#4a6cf7', '#7c3aed', '#db2777', '#6b7280', '#1a1a2e',
    ];

    private array $moduleLabels = [
        'users'       => 'Kullanıcılar',
        'roles'       => 'Roller',
        'permissions' => 'İzinler',
        'tenants'     => 'Kiracılar',
        'orders'      => 'Siparişler',
        'products'    => 'Ürünler',
        'invoices'    => 'Faturalar',
        'reports'     => 'Raporlar',
        'settings'    => 'Ayarlar',
        'system'      => 'Sistem',
    ];

    public function index(): Response
    {
        return Inertia::render('Superadmin::Settings', [
            'settings' => $this->buildSettingsPayload(),
            'options'  => $this->defaultOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::GROUPS as $group) {
            $rules[$group] = ['sometimes', 'array'];
        }
        $validated = $request->validate($rules);

        foreach (self::GROUPS as $group) {
            if (! array_key_exists($group, $validated)) {
                continue;
            }
            $values = $validated[$group] ?? [];

            // Maskelenmiş gelen hassas alanları yazma — kullanıcı değiştirmemiş.
            foreach ($values as $key => $val) {
                if ($val === self::MASK && Setting::isSensitive($group, $key)) {
                    unset($values[$key]);
                }
            }

            // Security grubu: log erişim şifresini bcrypt hash olarak sakla.
            // Frontend'den gelen "logAccessPassword" (plaintext) → hash → "logAccessPasswordHash".
            // Boş veya mask gelirse mevcut hash korunur.
            if ($group === 'security') {
                $plainPassword = $values['logAccessPassword'] ?? null;
                unset($values['logAccessPassword']); // plaintext asla DB'ye gitmesin
                // Hash'i de UI'a gönderilmiş hâliyle geri almayız; varsa koru, yoksa sil.
                unset($values['logAccessPasswordHash']);

                if (is_string($plainPassword) && $plainPassword !== '' && $plainPassword !== self::MASK) {
                    $values['logAccessPasswordHash'] = Hash::make($plainPassword);
                }
                // Boş / mask → hash değişmez; mevcut DB kaydı üzerine yazılmaz.
            }

            // Mail grubu DB yerine .env'e yazılır.
            if ($group === 'mail') {
                $this->writeMailEnv($values);
                continue;
            }

            Setting::setGroup($group, $values);
        }

        return back()->with('success', 'Ayarlar kaydedildi.');
    }

    /**
     * UI'daki mail form alanlarını .env'deki MAIL_* anahtarlarına eşleyip yazar.
     * Şifre maskelenmiş (MASK) gelirse atlanır. Yazımdan sonra config cache temizlenir.
     */
    private function writeMailEnv(array $values): void
    {
        static $fieldToEnv = [
            'driver'      => 'MAIL_MAILER',
            'encryption'  => 'MAIL_SCHEME',
            'host'        => 'MAIL_HOST',
            'port'        => 'MAIL_PORT',
            'username'    => 'MAIL_USERNAME',
            'password'    => 'MAIL_PASSWORD',
            'fromAddress' => 'MAIL_FROM_ADDRESS',
            'fromName'    => 'MAIL_FROM_NAME',
        ];

        // Mevcut .env içeriği üzerinden karşılaştır — env() helper'ı "null"
        // literal'ini PHP null'a çevirdiği için runtime karşılaştırma yanıltıcı
        // oluyor.
        $envPath    = base_path('.env');
        $envContent = is_file($envPath) ? (string) file_get_contents($envPath) : '';

        $updates = [];
        foreach ($values as $key => $val) {
            if (! isset($fieldToEnv[$key])) {
                continue;
            }
            $envKey = $fieldToEnv[$key];

            // Encryption "none" → env'de literal "null" string (Laravel bunu null'a çevirir).
            if ($key === 'encryption' && (string) $val === 'none') {
                $val = 'null';
            }

            $newVal      = (string) $val;
            $newLine     = $envKey . '=' . $this->escapeEnvValue($newVal);
            $lookupRegex = '/^' . preg_quote($envKey, '/') . '=.*$/m';

            // Aynı satır .env'de zaten varsa atla — gereksiz dosya I/O +
            // config:clear, Laragon/Windows'ta worker'ı düşürebiliyor.
            if (preg_match($lookupRegex, $envContent, $m) && $m[0] === $newLine) {
                continue;
            }

            $updates[$envKey] = $newVal;
        }

        if (empty($updates)) {
            return;
        }

        $this->writeEnvFile($updates);

        // Cached config varsa anında yansısın.
        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // sessiz geç — bir sonraki request'te zaten yeni env değeri okunur
        }
    }

    /**
     * .env içinde verilen anahtarları upsert eder. Mevcut satır varsa replace,
     * yoksa dosyanın sonuna ekler. Boşluk/özel karakter içeren değerler quote'lanır.
     */
    private function writeEnvFile(array $updates): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            throw new RuntimeException('.env dosyası bulunamadı.');
        }
        if (! is_writable($path)) {
            throw new RuntimeException('.env dosyası yazılabilir değil — dosya izinlerini kontrol edin.');
        }

        $content = (string) file_get_contents($path);

        foreach ($updates as $key => $value) {
            $escaped = $this->escapeEnvValue($value);
            $line    = "{$key}={$escaped}";
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;
            }
        }

        file_put_contents($path, $content, LOCK_EX);
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        // Quote gerektiren durumlar: boşluk, tırnak, #, =, $ (interpolation)
        if (preg_match('/[\s"\'#=$]/', $value)) {
            $value = str_replace(['\\', '"'], ['\\\\', '\"'], $value);
            return '"' . $value . '"';
        }
        return $value;
    }

    private function buildSettingsPayload(): array
    {
        $defaults = $this->defaultSettings();
        $stored   = Setting::allGrouped(); // ham (hassas alanlar şifreli)

        $payload = [];
        foreach (self::GROUPS as $group) {
            // Mail tamamen .env'den okunur; DB'deki eski kayıtlar görmezden gelinir.
            $values = $group === 'mail'
                ? ($defaults[$group] ?? [])
                : array_merge($defaults[$group] ?? [], $stored[$group] ?? []);

            $payload[$group] = $this->maskSensitive($group, $values);
        }

        // Security: log erişim hash'ini frontend'e asla göndermiyoruz.
        // Bunun yerine, şifrenin belirlenip belirlenmediğini boolean olarak iletiriz.
        $securityStored = $stored['security'] ?? [];
        $hashValue      = $securityStored['logAccessPasswordHash'] ?? null;
        unset($payload['security']['logAccessPasswordHash']);
        $payload['security']['logAccessPasswordSet'] = filled($hashValue);

        $payload['roles']  = $this->buildRolesPayload();
        $payload['system'] = $this->buildSystemPayload();

        return $payload;
    }

    /**
     * Hassas alanların değerini UI'a göndermeden önce maskele.
     * Şifreli string'in kendisini de plain text'i de UI'a hiçbir zaman vermiyoruz.
     */
    private function maskSensitive(string $group, array $values): array
    {
        foreach ($values as $key => $value) {
            if (Setting::isSensitive($group, $key) && is_string($value) && $value !== '') {
                $values[$key] = self::MASK;
            }
        }
        return $values;
    }

    private function buildRolesPayload(): array
    {
        $roles = Role::with('permissions')->get()->values()->map(function (Role $role, int $i) {
            return [
                'id'          => $role->id,
                'key'         => $role->name,
                'name'        => $role->display_name ?: $role->name,
                'desc'        => '',
                'color'       => $this->roleColors[$i % count($this->roleColors)],
                'userCount'   => $role->users()->count(),
                'system'      => $role->name === 'superadmin',
                'permissions' => $role->permissions->pluck('name')->all(),
            ];
        })->all();

        $modules = Permission::all()
            ->groupBy(fn (Permission $p) => str_contains($p->name, '.') ? explode('.', $p->name)[0] : 'general')
            ->map(function ($perms, $moduleKey) {
                return [
                    'key'         => $moduleKey,
                    'name'        => $this->moduleLabels[$moduleKey] ?? ucfirst($moduleKey),
                    'permissions' => $perms->map(fn (Permission $p) => [
                        'id'   => $p->id,
                        'key'  => $p->name,
                        'name' => $p->display_name ?: $p->name,
                        'desc' => '',
                    ])->values()->all(),
                ];
            })->values()->all();

        return [
            'list'    => $roles,
            'modules' => $modules,
        ];
    }

    private function buildSystemPayload(): array
    {
        // Initial render'ı PowerShell (Get-CimInstance) çağrılarıyla bloklamamak
        // için statik payload döndürülür; CPU/RAM/uptime polling endpoint
        // (/superadmin/system-info) tarafından doldurulur.
        return app(SystemInfoService::class)->staticPayload();
    }

    /**
     * .env'de "null" string'i Laravel tarafından null'a çevriliyor; UI'a boş string
     * gönderiyoruz ki form input controlled kalsın.
     */
    private function envString(string $key): string
    {
        $val = env($key);
        return $val === null ? '' : (string) $val;
    }

    /**
     * MAIL_SCHEME değerlerini UI dropdown'una (tls/ssl/none) eşle.
     */
    private function normalizeMailScheme(mixed $scheme): string
    {
        $s = is_string($scheme) ? strtolower(trim($scheme)) : '';
        return match ($s) {
            'ssl', 'smtps' => 'ssl',
            'tls', 'starttls', 'smtp' => 'tls',
            default => 'none',
        };
    }

    public function defaultSettings(): array
    {
        return [
            'general' => [
                'systemName'         => config('app.name', 'Laravel'),
                'systemUrl'          => config('app.url', 'http://localhost'),
                'supportEmail'       => 'destek@example.com',
                'logoUrl'            => '',
                'defaultLanguage'    => 'tr',
                'defaultTimezone'    => 'Europe/Istanbul',
                'dateFormat'         => 'd.m.Y',
                'defaultCurrency'    => 'TRY',
                'allowSignup'        => true,
                'maintenanceMode'    => false,
                'maintenanceMessage' => '',
            ],
            'security' => [
                'passwordMinLength'        => 8,
                'passwordExpiryDays'       => 90,
                'passwordRequireUppercase' => true,
                'passwordRequireNumbers'   => true,
                'passwordRequireSpecial'   => false,
                'twoFactorRequired'        => 'optional',
                'sessionTimeoutMinutes'    => 60,
                'maxLoginAttempts'         => 5,
                'lockoutMinutes'           => 15,
                'ipWhitelist'              => '',
                'enableAuditLog'           => true,
                'enableCaptcha'            => false,
            ],
            // .env single source of truth — bkz. update() ve writeMailEnv().
            'mail' => [
                'driver'         => env('MAIL_MAILER', 'smtp'),
                'encryption'     => $this->normalizeMailScheme(env('MAIL_SCHEME')),
                'host'           => (string) env('MAIL_HOST', ''),
                'port'           => (int) env('MAIL_PORT', 587),
                'username'       => $this->envString('MAIL_USERNAME'),
                'password'       => $this->envString('MAIL_PASSWORD'),
                'fromAddress'    => (string) env('MAIL_FROM_ADDRESS', ''),
                'fromName'       => (string) env('MAIL_FROM_NAME', config('app.name', 'Laravel')),
                'lastTestResult' => null,
                'lastTestedAt'   => null,
            ],
            'notifications' => [
                'emailNewTenant'           => true,
                'emailNewOrder'            => true,
                'emailPaymentFailure'      => true,
                'emailSystemError'         => true,
                'emailWeeklyReport'        => false,
                'slackWebhookUrl'          => '',
                'slackChannel'             => '',
                'enablePushNotifications'  => false,
                'enableInAppNotifications' => true,
            ],
            'billing' => [
                'paymentProvider' => 'iyzico',
                'iyzicoApiKey'    => '',
                'iyzicoSecretKey' => '',
                'stripePublicKey' => '',
                'stripeSecretKey' => '',
                'currency'        => 'TRY',
                'vatRate'         => 20,
                'trialDays'       => 14,
                'invoicePrefix'   => 'INV-',
                'invoiceFooter'   => '',
                'sandboxMode'     => true,
            ],
            'storage' => [
                'driver'              => 'local',
                'bucket'              => '',
                'region'              => '',
                'accessKey'           => '',
                'secretKey'           => '',
                'cdnUrl'              => '',
                'maxUploadMB'         => 25,
                'allowedExtensions'   => 'jpg,jpeg,png,pdf,docx,xlsx',
                'backupEnabled'       => false,
                'backupSchedule'      => 'daily',
                'backupTime'          => '03:00',
                'backupRetentionDays' => 30,
                'lastBackupAt'        => null,
                'lastBackupSize'      => null,
            ],
            'api' => [
                'rateLimitPerMinute' => 60,
                'apiVersion'         => 'v1',
                'webhookSecret'      => '',
                'allowedOrigins'     => '*',
                'sandboxMode'        => false,
                'requireApiKey'      => true,
                'enableSwagger'      => true,
            ],
            'performance' => [
                'cacheDriver'      => 'file',
                'cacheTtlMinutes'  => 60,
                'queueDriver'      => 'sync',
                'queueWorkers'     => 1,
                'sessionDriver'    => 'file',
                'logLevel'         => 'info',
                'logRetentionDays' => 14,
                'enableDebugBar'   => false,
                'enableQueryLog'   => false,
            ],
        ];
    }

    public function defaultOptions(): array
    {
        return [
            'languages' => [
                ['value' => 'tr', 'label' => 'Türkçe'],
                ['value' => 'en', 'label' => 'English'],
            ],
            'timezones' => [
                ['value' => 'Europe/Istanbul',  'label' => 'Europe/Istanbul (UTC+3)'],
                ['value' => 'UTC',              'label' => 'UTC'],
                ['value' => 'Europe/London',    'label' => 'Europe/London'],
                ['value' => 'America/New_York', 'label' => 'America/New_York'],
            ],
            'dateFormats' => [
                ['value' => 'd.m.Y', 'label' => '31.12.2026'],
                ['value' => 'Y-m-d', 'label' => '2026-12-31'],
                ['value' => 'm/d/Y', 'label' => '12/31/2026'],
                ['value' => 'd M Y', 'label' => '31 Ara 2026'],
            ],
            'currencies' => [
                ['value' => 'TRY', 'label' => 'TRY — Türk Lirası'],
                ['value' => 'USD', 'label' => 'USD — US Dollar'],
                ['value' => 'EUR', 'label' => 'EUR — Euro'],
                ['value' => 'GBP', 'label' => 'GBP — British Pound'],
            ],
            'twoFactorOptions' => [
                ['value' => 'optional', 'label' => 'İsteğe bağlı'],
                ['value' => 'admins',   'label' => 'Yöneticiler için zorunlu'],
                ['value' => 'required', 'label' => 'Tüm kullanıcılar için zorunlu'],
            ],
            'mailDrivers' => [
                ['value' => 'smtp',     'label' => 'SMTP'],
                ['value' => 'mailgun',  'label' => 'Mailgun'],
                ['value' => 'ses',      'label' => 'Amazon SES'],
                ['value' => 'postmark', 'label' => 'Postmark'],
                ['value' => 'log',      'label' => 'Log (test)'],
            ],
            'mailEncryption' => [
                ['value' => 'tls',  'label' => 'TLS'],
                ['value' => 'ssl',  'label' => 'SSL'],
                ['value' => 'none', 'label' => 'Yok'],
            ],
            'paymentProviders' => [
                ['value' => 'iyzico', 'label' => 'iyzico'],
                ['value' => 'stripe', 'label' => 'Stripe'],
            ],
            'storageDrivers' => [
                ['value' => 'local', 'label' => 'Local'],
                ['value' => 's3',    'label' => 'Amazon S3'],
                ['value' => 'gcs',   'label' => 'Google Cloud Storage'],
            ],
            'backupSchedules' => [
                ['value' => 'hourly',  'label' => 'Saatlik'],
                ['value' => 'daily',   'label' => 'Günlük'],
                ['value' => 'weekly',  'label' => 'Haftalık'],
                ['value' => 'monthly', 'label' => 'Aylık'],
            ],
            'cacheDrivers' => [
                ['value' => 'file',      'label' => 'File'],
                ['value' => 'redis',     'label' => 'Redis'],
                ['value' => 'memcached', 'label' => 'Memcached'],
                ['value' => 'database',  'label' => 'Database'],
                ['value' => 'array',     'label' => 'Array'],
            ],
            'queueDrivers' => [
                ['value' => 'sync',     'label' => 'Sync'],
                ['value' => 'database', 'label' => 'Database'],
                ['value' => 'redis',    'label' => 'Redis'],
                ['value' => 'sqs',      'label' => 'Amazon SQS'],
            ],
            'logLevels' => [
                ['value' => 'debug',    'label' => 'Debug'],
                ['value' => 'info',     'label' => 'Info'],
                ['value' => 'notice',   'label' => 'Notice'],
                ['value' => 'warning',  'label' => 'Warning'],
                ['value' => 'error',    'label' => 'Error'],
                ['value' => 'critical', 'label' => 'Critical'],
            ],
        ];
    }
}
