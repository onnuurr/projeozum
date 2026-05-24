<?php

namespace Modules\Superadmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Setting extends Model
{
    protected $table = 'superadmin_settings';

    protected $fillable = ['group', 'key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    public const CACHE_KEY = 'superadmin.settings.all';
    public const CACHE_TTL = 3600;

    /**
     * KVKK gereği şifreli (rest'te encrypted) saklanacak alanlar.
     * Format: "group.key"
     */
    public const SENSITIVE_KEYS = [
        'mail.password',
        'billing.iyzicoApiKey',
        'billing.iyzicoSecretKey',
        'billing.stripePublicKey',
        'billing.stripeSecretKey',
        'storage.accessKey',
        'storage.secretKey',
        'api.webhookSecret',
    ];

    public static function isSensitive(string $group, string $key): bool
    {
        return in_array("$group.$key", self::SENSITIVE_KEYS, true);
    }

    /**
     * Ham (şifreli alanlar şifreli) tüm ayarları döner. Cache'e bu form alınır,
     * yani cache leak'i durumunda dahi hassas değerler plain text olmaz.
     */
    public static function allGrouped(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = static::query()->get(['group', 'key', 'value']);
            $out  = [];
            foreach ($rows as $row) {
                $out[$row->group][$row->key] = $row->value;
            }
            return $out;
        });
    }

    /**
     * Bir grubu, hassas alanlar çözülmüş (decrypted) olarak döner.
     * Yalnızca secret'a gerçekten ihtiyaç duyan kod yolları (mail driver,
     * payment driver, vb.) bu metodu çağırmalı.
     */
    public static function getGroup(string $group): array
    {
        $values = self::allGrouped()[$group] ?? [];

        foreach ($values as $key => $value) {
            if (! self::isSensitive($group, $key) || ! is_string($value) || $value === '') {
                continue;
            }
            try {
                $values[$key] = Crypt::decryptString($value);
            } catch (Throwable) {
                $values[$key] = ''; // bozuk/legacy plain text → yeniden girilmesi gerek
            }
        }

        return $values;
    }

    public static function setGroup(string $group, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $now  = now();
        $rows = [];
        foreach ($values as $key => $value) {
            if (self::isSensitive($group, $key) && is_string($value) && $value !== '') {
                $value = Crypt::encryptString($value);
            }
            $rows[] = [
                'group'      => $group,
                'key'        => (string) $key,
                'value'      => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Tek SQL: INSERT ... ON CONFLICT (group,key) DO UPDATE — N kayıt 1 query
        static::query()->upsert($rows, ['group', 'key'], ['value', 'updated_at']);

        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        // Tekil save/delete için cache invalidation. Toplu upsert event'leri tetiklemez,
        // o yol kendi içinde Cache::forget yapıyor.
        $flush = static fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }
}
