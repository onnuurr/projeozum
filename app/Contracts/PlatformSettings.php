<?php

namespace App\Contracts;

/**
 * Süper admin platform ayarlarına (superadmin_settings) modül-bağımsız erişim. Superadmin'e
 * bağımlı olamayan modüller (ör. Bagisto — bkz. deptrac.php) ayar okuyup yazmak için bu kernel
 * sözleşmesini kullanır; tek sürücü Modules\Superadmin\Services\PlatformSettingsStore
 * (SuperadminServiceProvider::register()).
 *
 * Başka bir modülün kendi ayarlarını sakladığı gruplar (ör. Bagisto'nun 'bagisto_features')
 * group()/putGroup() ile okunur/yazılır.
 */
interface PlatformSettings
{
    /**
     * Bir grubun kayıtlı değerleri (varsayılanlar birleştirilmez; hassas alanlar çözülmüş gelir).
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array;

    /**
     * Verilen anahtarları gruba yazar (upsert); gönderilmeyen anahtarlara dokunmaz.
     *
     * @param  array<string, mixed>  $values
     */
    public function putGroup(string $group, array $values): void;
}
