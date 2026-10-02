<?php

namespace Modules\Bagisto\Services;

use App\Contracts\TranslationSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Bagisto'nun dil dosyaları (paket Resources/lang'ları, lang/ ve lang/vendor/* override'ları).
 * Dosyalar ayrı Unix kullanıcısında (ozumserver-bg) olduğu için SaaS doğrudan erişmez; Bagisto
 * tarafındaki Webkul\SaasSync\Services\TranslationFiles imzalı api/saas-sync/translations/*
 * uç noktalarıyla okur/yazar. Bagisto'da yeni dosya oluşturulmaz (paket kodu yüklemez).
 */
class BagistoTranslationSource implements TranslationSource
{
    public function __construct(private readonly BagistoSyncClient $client) {}

    public function key(): string
    {
        return 'bagisto';
    }

    public function label(): string
    {
        return 'Bagisto (bayi mağazası)';
    }

    public function allowsNewFiles(): bool
    {
        return false;
    }

    public function groups(): array
    {
        return $this->call('api/saas-sync/translations/groups', [])['groups'] ?? [];
    }

    public function read(string $group, string $file, array $locales): array
    {
        return $this->call('api/saas-sync/translations/read', [
            'group' => $group,
            'file' => $file,
            'locales' => array_values($locales),
        ])['rows'] ?? [];
    }

    public function write(string $group, string $file, array $changes, bool $fillMissingLocales, ?int $actorId): int
    {
        return (int) ($this->call('api/saas-sync/translations/write', [
            'group' => $group,
            'file' => $file,
            'changes' => $changes,
            'fill_missing_locales' => $fillMissingLocales,
            'actor' => $actorId,
        ])['written'] ?? 0);
    }

    /**
     * Bagisto'nun doğrulama hatası (422) → InvalidArgumentException (kullanıcıya gösterilir);
     * ulaşılamıyorsa / yapılandırılmamışsa → RuntimeException.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $path, array $payload): array
    {
        try {
            return $this->client->request($path, $payload, 20);
        } catch (RequestException $e) {
            if ($e->response->status() === 422) {
                throw new InvalidArgumentException((string) ($e->response->json('message') ?? 'Bagisto isteği reddetti.'));
            }

            throw new RuntimeException('Bagisto dil dosyası isteği başarısız (HTTP '.$e->response->status().').', 0, $e);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Bagisto\'ya ulaşılamadı: '.$e->getMessage(), 0, $e);
        }
    }
}
