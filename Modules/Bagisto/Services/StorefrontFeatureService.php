<?php

namespace Modules\Bagisto\Services;

use App\Contracts\PlatformSettings;
use Modules\Bagisto\Jobs\PushStorefrontFeaturesToBagisto;

/**
 * Bayi portalı (Bagisto mağazası) özellik anahtarları. SaaS tek doğruluk kaynağıdır: değerler
 * platform ayarlarında (App\Contracts\PlatformSettings) 'bagisto_features' grubunda saklanır ve her kayıtta mağazaya imzalı
 * webhook'la gönderilir (Webkul\BayiPortal\Http\Controllers\Api\FeatureWebhookController).
 *
 * Yeni özellik: buraya ekle + mağazada Webkul\BayiPortal\Support\Features::defaults() içine aynı
 * anahtarı ekle (mağaza bilmediği anahtarı yok sayar).
 */
class StorefrontFeatureService
{
    public const GROUP = 'bagisto_features';

    public const STATUS_GROUP = 'bagisto_sync';

    /**
     * Bu sayfada değil, Süper Admin › Ayarlar › Genel › "Yeni kayıt açık" (general.allowSignup) ile
     * yönetilen ama mağazaya aynı kanaldan giden anahtar: kapalıyken bayilik başvuru formu kapanır.
     */
    public const DEALER_APPLICATIONS = 'dealer_applications';

    public function __construct(private PlatformSettings $settings) {}

    /**
     * @return array<string, array{label: string, description: string, default: bool, group: string}>
     */
    public function catalog(): array
    {
        return [
            'bulk_order_excel' => [
                'group' => 'Sipariş',
                'label' => 'Excel ile toplu sipariş',
                'description' => 'Hızlı Sipariş sayfasında Excel\'den yapıştırma ve CSV/XLSX dosya yükleme; mağazadaki "Excel ile toplu sipariş" bağlantıları. Kapalıyken bayiler yalnızca SKU + adet girerek hızlı sipariş verebilir.',
                'default' => false,
            ],
            'cari_account' => [
                'group' => 'Ödeme',
                'label' => 'Cari hesap ve bakiyeden ödeme',
                'description' => '"Cari Bakiyeden Öde" ödeme yöntemi, Cari Hesap sayfası, üst çubuktaki bakiye bilgisi ve panellerdeki limit/bakiye kartları. İş ortağında kredi limiti tanımlı olmalıdır.',
                'default' => false,
            ],
            'hide_prices_for_guests' => [
                'group' => 'Katalog',
                'label' => 'Fiyatları yalnızca giriş yapan bayilere göster',
                'description' => 'Misafir ziyaretçiler kataloğu fiyatsız gezer ve sepete ürün ekleyemez.',
                'default' => true,
            ],
        ];
    }

    /**
     * Mağazaya gidecek değerlerin tamamı (katalog + yeni başvuru anahtarı).
     *
     * @return array<string, bool>
     */
    public function values(): array
    {
        $stored = $this->settings->group(self::GROUP);

        return collect($this->catalog())
            ->map(fn (array $feature, string $key) => (bool) ($stored[$key] ?? $feature['default']))
            ->put(self::DEALER_APPLICATIONS, $this->applicationsOpen())
            ->all();
    }

    /** Süper admin "Yeni kayıt açık" ayarı. */
    public function applicationsOpen(): bool
    {
        return $this->settings->allowsSignup();
    }

    /**
     * @param  array<string, bool>  $values  yalnızca katalogdaki anahtarlar dikkate alınır
     */
    public function update(array $values): void
    {
        $known = collect($values)->only(array_keys($this->catalog()))->map(fn ($value) => (bool) $value)->all();

        $this->settings->putGroup(self::GROUP, $known);

        $this->push();
    }

    /** Geçerli değerlerin tamamını mağazaya gönderir (kayıtta ve "Şimdi senkronize et" ile). */
    public function push(): void
    {
        $this->settings->putGroup(self::STATUS_GROUP, ['features_sync_state' => 'pending', 'features_sync_requested_at' => now()->toIso8601String()]);

        PushStorefrontFeaturesToBagisto::dispatch();
    }

    /**
     * @return array{state: string|null, requested_at: string|null, synced_at: string|null, error: string|null}
     */
    public function syncStatus(): array
    {
        $status = $this->settings->group(self::STATUS_GROUP);

        return [
            'state' => $status['features_sync_state'] ?? null,
            'requested_at' => $status['features_sync_requested_at'] ?? null,
            'synced_at' => $status['features_synced_at'] ?? null,
            'error' => $status['features_sync_error'] ?? null,
        ];
    }

    public function markSynced(): void
    {
        $this->settings->putGroup(self::STATUS_GROUP, [
            'features_sync_state' => 'synced',
            'features_synced_at' => now()->toIso8601String(),
            'features_sync_error' => '',
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->settings->putGroup(self::STATUS_GROUP, [
            'features_sync_state' => 'failed',
            'features_sync_error' => mb_substr($error, 0, 500),
        ]);
    }
}
