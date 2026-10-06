<?php

namespace Modules\Creative\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Google Gemini API (AI Studio), fal.ai'nin aksine "kalan bakiye" döndüren bir
 * uç nokta sunmuyor — prepay bakiyesi sadece AI Studio panelinden görülebiliyor.
 * Bunun yerine bu sınıf, GCP Billing Export'a (BigQuery) bağlanıp bu
 * faturalandırma AYINDA Gemini API için BİRİKEN (accrued) toplam maliyeti
 * okur — "kalan kredi" değil, "bu ay şu ana kadar ne kadar harcandı".
 *
 * ÜÇ config değerinden biri boşsa (creative.ai.gemini.billing.*) hiç
 * BigQuery'e gitmeden `null` döner — Vue tarafı rozeti hiç göstermez. Bu üç
 * değer GCP Console'da elle hazırlanmış bir ön koşula bağlı (bu serviste
 * yapılamaz):
 *  1) Billing → Billing export → BigQuery export bir dataset'e bağlanmış olmalı
 *     (ilk etkinleştirmeden sonra verinin akması ~24 saat sürebilir).
 *  2) O dataset'e en az BigQuery Data Viewer + Job User rolüne sahip bir
 *     servis hesabı oluşturulmuş olmalı.
 *  3) O servis hesabının JSON key dosyası sunucuda erişilebilir bir yolda
 *     durmalı (.env'e KOYULMAZ, sadece dosya yolu env'de tutulur).
 */
class GeminiSpendService
{
    private const CACHE_KEY = 'creative:gemini-spend';

    // BigQuery sorgusu taranan byte başına ücretlidir — fal bakiyesinden
    // (300sn) bile daha seyrek çağrılsın diye 30 dakika cache'lenir.
    private const CACHE_TTL = 1800;

    /**
     * @return array{amount:?float,currency:?string,error:?string}|null
     */
    public function currentMonthSpend(): ?array
    {
        $projectId   = (string) config('creative.ai.gemini.billing.project_id');
        $dataset     = (string) config('creative.ai.gemini.billing.dataset');
        $credentials = (string) config('creative.ai.gemini.billing.credentials_path');

        if ($projectId === '' || $dataset === '' || $credentials === '' || ! is_file($credentials)) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($projectId, $dataset, $credentials) {
            try {
                $bq = new BigQueryClient([
                    'projectId'   => $projectId,
                    'keyFilePath' => $credentials,
                ]);

                $table = $this->resolveTable($bq, $dataset);
                if ($table === null) {
                    return ['amount' => null, 'currency' => null, 'error' => 'Billing export tablosu dataset içinde bulunamadı.'];
                }

                $services = (array) config('creative.ai.gemini.billing.service_descriptions', ['Gemini API', 'Generative Language API']);

                $sql = sprintf(
                    'SELECT SUM(cost) AS gross_cost,'
                    . ' SUM(IFNULL((SELECT SUM(c.amount) FROM UNNEST(credits) AS c), 0)) AS credits_total,'
                    . ' ANY_VALUE(currency) AS currency'
                    . ' FROM `%s.%s.%s`'
                    . ' WHERE service.description IN UNNEST(@services) AND invoice.month = @month',
                    $projectId,
                    $dataset,
                    $table,
                );

                $query = $bq->query($sql)->parameters([
                    'services' => array_values($services),
                    // GCP billing export'ta invoice.month "YYYYMM" formatındadır.
                    'month' => now()->format('Ym'),
                ]);

                $rows = iterator_to_array($bq->runQuery($query));
                $row  = $rows[0] ?? null;

                if ($row === null || $row['gross_cost'] === null) {
                    // O ay için hiç Gemini satırı yok demektir — 0 harcama, hata değil.
                    return ['amount' => 0.0, 'currency' => 'USD', 'error' => null];
                }

                $net = (float) $row['gross_cost'] + (float) ($row['credits_total'] ?? 0);

                return [
                    'amount'   => round($net, 2),
                    'currency' => $row['currency'] ?? 'USD',
                    'error'    => null,
                ];
            } catch (Throwable $e) {
                return ['amount' => null, 'currency' => null, 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Export tablosunun tam adı billing account ID'sini içerir (ör.
     * "gcp_billing_export_v1_XXXXXX_XXXXXX_XXXXXX") — kullanıcının elle
     * girmesine gerek kalmasın diye dataset içinde bu prefiksle başlayan İLK
     * tablo otomatik keşfedilir. `creative.ai.gemini.billing.table` doluysa
     * (keşif atlanır) doğrudan o kullanılır.
     */
    private function resolveTable(BigQueryClient $bq, string $dataset): ?string
    {
        $configured = (string) config('creative.ai.gemini.billing.table');
        if ($configured !== '') {
            return $configured;
        }

        foreach ($bq->dataset($dataset)->tables() as $table) {
            $id = $table->id();
            if (str_starts_with($id, 'gcp_billing_export_v1_') || str_starts_with($id, 'gcp_billing_export_resource_v1_')) {
                return $id;
            }
        }

        return null;
    }
}
