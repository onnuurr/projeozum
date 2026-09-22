<?php

namespace Modules\PatternEngine\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Modules\PatternEngine\Http\Requests\ImportPatternFromUrlRequest;
use Modules\PatternEngine\Jobs\ExtractPatternFromPdfJob;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Services\Conversion\Contracts\PdfDxfConverterContract;

/**
 * Sabit-domain tedarikçi ürün sayfalarından ("vikroim.ru" tarzı — bkz.
 * config/atelier.php pattern_import.allowed_hosts) kalıp PDF'ini ve bir önizleme
 * görselini otomatik bulup indirir, PatternController::importPdf ile AYNI akışı
 * (createPdfDraft + ExtractPatternFromPdfJob) kullanarak taslak kalıp oluşturur.
 *
 * Bilinen sayfa şablonu (WordPress "VK içe aktarma" eklentisi):
 *   PDF ekleri : <div class="vk-att"><div class="vk-att-item"><a href="...file=...pdf">
 *   Galeri     : <div class="vkwpb_img_wrap"><img src="...">
 * Şablon eşleşmezse önce genel ".pdf" linki / og:image'e düşülür.
 *
 * ÖNEMLİ — vk.com dolaylaması: "PDF" linki aslında vikroim.ru'da barınmıyor,
 * vk.com/vk.ru'daki bir Mail.ru Cloud belge görüntüleyicisine 302 yönleniyor.
 * O görüntüleyici düz HTTP isteklerini engelliyor (bot koruması, HTTP 418) ve
 * gerçek indirme linkini yalnızca JS ile "Dosyayı indir" tıklaması sonrası
 * üretiyor. Bu yüzden PDF için gerçek bir headless tarayıcı gerekiyor — bkz.
 * resolvers/vk-doc/resolve.mjs (izole Playwright/Chromium alt-projesi,
 * ana Vite derlemesinden bağımsız) ve resolveViaHeadlessBrowser() aşağıda.
 */
class PatternUrlImportService
{
    private const MAX_HTML_BYTES  = 5 * 1024 * 1024;
    private const MAX_PDF_BYTES   = 51200 * 1024; // importPdf ile aynı üst sınır (bkz. PatternController)
    private const MAX_IMAGE_BYTES = 5120 * 1024;  // preview_image kuralıyla aynı üst sınır
    private const USER_AGENT      = 'Mozilla/5.0 (compatible; OzumServerAtelierBot/1.0)';

    public function __construct(
        private readonly PatternLibraryService $library,
        private readonly PdfDxfConverterContract $converter,
    ) {}

    public function importFromUrl(string $sourceUrl, ?int $createdBy = null): Pattern
    {
        // Sayfa taraması + olası headless tarayıcı çözümü + iki dosya indirmesi
        // PHP-FPM'in varsayılan max_execution_time'ına (90s) yakın sürebilir.
        @set_time_limit(150);

        $html = $this->fetch($sourceUrl, self::MAX_HTML_BYTES);

        $pdfUrl = $this->extractPdfUrl($html, $sourceUrl);
        if ($pdfUrl === null) {
            throw new \RuntimeException('Sayfada PDF eki bulunamadı.');
        }

        $pdfTmp = null;
        $imgTmp = null;

        try {
            [$pdfBytes, $suggestedName] = $this->resolvePdfBytes($pdfUrl);
            if (! str_starts_with($pdfBytes, '%PDF-')) {
                throw new \RuntimeException('İndirilen dosya geçerli bir PDF değil.');
            }

            [$pdfFile, $pdfTmp] = $this->asUploadedFile(
                $pdfBytes,
                $suggestedName ?? $this->filenameFromUrl($pdfUrl, 'kalip.pdf'),
                'application/pdf',
            );

            $probe = $this->converter->probe($pdfFile->getRealPath());
            if (in_array($probe['kind'] ?? null, ['empty', 'invalid'], true)) {
                throw new \RuntimeException('İndirilen PDF boş veya okunamıyor (taranmış/geçersiz olabilir).');
            }

            $pattern = $this->library->createPdfDraft($pdfFile, $createdBy);

            // Görsel opsiyonel — bulunamaz/indirilemezse taslak PDF'siz değil,
            // yalnızca önizlemesiz kalır; akış PDF içe aktarımını yine tamamlar.
            foreach ($this->extractImageUrls($html, $sourceUrl, 2) as $imageUrl) {
                try {
                    $imgBytes = $this->fetch($imageUrl, self::MAX_IMAGE_BYTES);
                    $info     = @getimagesizefromstring($imgBytes);
                    if ($info === false) {
                        continue;
                    }

                    [$imgFile, $imgTmp] = $this->asUploadedFile(
                        $imgBytes,
                        $this->filenameFromUrl($imageUrl, 'onizleme.jpg'),
                        $info['mime'],
                    );
                    $this->library->update($pattern, [], ['preview_image' => $imgFile], [], []);
                    break;
                } catch (\Throwable) {
                    continue;
                } finally {
                    if ($imgTmp !== null && is_file($imgTmp)) {
                        @unlink($imgTmp);
                    }
                    $imgTmp = null;
                }
            }

            ExtractPatternFromPdfJob::dispatch($pattern->id);

            return $pattern->fresh();
        } finally {
            if ($pdfTmp !== null && is_file($pdfTmp)) {
                @unlink($pdfTmp);
            }
        }
    }

    /**
     * @return array{0:string,1:?string} [pdf bytes, önerilen dosya adı (varsa)]
     */
    private function resolvePdfBytes(string $pdfUrl): array
    {
        $this->assertAllowedHost($pdfUrl);

        // Yönlendirmeyi TAKİP ETMEDEN hedefi öğren: vikroim.ru → vk.com/vk.ru
        // dolaylaması varsa düz istekle indirilemez (bkz. sınıf docblock'u).
        $probe = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->withOptions(['allow_redirects' => false])
            ->timeout(15)->connectTimeout(10)
            ->get($pdfUrl);

        $location = $probe->header('Location');
        if ($probe->status() >= 300 && $probe->status() < 400 && $location
            && preg_match('#^https://(vk\.com|vk\.ru)/#i', $location)) {
            return $this->resolveViaHeadlessBrowser($location);
        }

        return [$this->fetch($pdfUrl, self::MAX_PDF_BYTES), null];
    }

    /**
     * vk.com/vk.ru belge görüntüleyicisindeki "Dosyayı indir" tıklamasını izole
     * bir Playwright/Chromium alt-süreciyle (resolvers/vk-doc/resolve.mjs) yapar
     * ve indirilen dosyayı okur.
     *
     * @return array{0:string,1:?string} [pdf bytes, önerilen dosya adı]
     */
    private function resolveViaHeadlessBrowser(string $vkDocUrl): array
    {
        $scriptDir = base_path('Modules/Atelier/resolvers/vk-doc');
        $outPath   = tempnam(sys_get_temp_dir(), 'atelier_vkdoc_');
        $timeout   = (int) config('patternengine.pattern_import.headless_timeout', 60);

        try {
            // PLAYWRIGHT_BROWSERS_PATH=0: Chromium'u node_modules/playwright-core/.local-browsers
            // içinden (kurulumda oraya indirildi) kullanmaya zorlar. Bu env değişkeni
            // burada AÇIKÇA verilmezse Playwright çalıştıran kullanıcının $HOME'una göre
            // global ms-playwright önbelleğine bakar — o kullanıcı için boş olabilir
            // (PHP-FPM 'ozumserver' olarak çalışıyor, kurulum kök/başka kullanıcıyla
            // yapılmış olabilir) ve "Executable doesn't exist" hatası verir.
            $result = Process::path($scriptDir)
                ->env(['PLAYWRIGHT_BROWSERS_PATH' => '0'])
                ->timeout($timeout)
                ->run(['node', 'resolve.mjs', $vkDocUrl, $outPath]);

            $json = json_decode(trim((string) $result->output()), true);
            if (! is_array($json) || ($json['ok'] ?? false) !== true) {
                $error = is_array($json) ? ($json['error'] ?? null) : null;
                throw new \RuntimeException(
                    'VK belgesi çözülemedi: ' . ($error ?? trim((string) $result->errorOutput()) ?: 'bilinmeyen hata'),
                );
            }

            $bytes = is_file($outPath) ? file_get_contents($outPath) : false;
            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('VK belgesi indirildi ama okunamadı.');
            }
            if (strlen($bytes) > self::MAX_PDF_BYTES) {
                throw new \RuntimeException('VK belgesi izin verilen boyutu aşıyor.');
            }

            return [$bytes, is_string($json['filename'] ?? null) ? $json['filename'] : null];
        } finally {
            if (is_file($outPath)) {
                @unlink($outPath);
            }
        }
    }

    private function fetch(string $url, int $maxBytes): string
    {
        $this->assertAllowedHost($url);

        $timeout  = (int) config('patternengine.pattern_import.timeout', 20);
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; OzumServerAtelierBot/1.0)'])
            ->timeout($timeout)
            ->connectTimeout(10)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("İçerik indirilemedi (HTTP {$response->status()}): {$url}");
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            throw new \RuntimeException("İndirilen dosya izin verilen boyutu aşıyor: {$url}");
        }

        return $body;
    }

    private function assertAllowedHost(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = parse_url($url, PHP_URL_HOST);
        $allowed = (array) config('patternengine.pattern_import.allowed_hosts', []);

        if ($scheme !== 'https' || $host === null || ! ImportPatternFromUrlRequest::hostAllowed($host, $allowed)) {
            throw new \RuntimeException("İzinli olmayan kaynak: {$url}");
        }
    }

    /**
     * "İnşrukция"/"Instruktsia" (dikiş talimatı) eki, "выкройка"/"kroy" (gerçek
     * kesim şablonu) ekinden AYRI yayınlanan sayfalarda ikisi de vk-att-item
     * olarak listelenebilir — bkz. şkatulka-sew.ru "Тим215" vakası (yalnızca
     * talimat eki linklenmişti, ama başka satıcılarda ikisi de olabilir).
     * Bu isim parçalarını taşıyan bir ek varsa ATLANIR, ilk uygun (talimat
     * OLMAYAN) ek seçilir; hiçbiri uymuyorsa yine de ilk bulunan kullanılır
     * (tek ek her zaman talimat değildir — bkz. gerçek Ruslan/Burda vakaları).
     */
    private const INSTRUCTION_FILE_HINTS = ['инструкция', 'instruktsia', 'instrukciya', 'instruction'];

    private function extractPdfUrl(string $html, string $baseUrl): ?string
    {
        if (preg_match_all('/class="vk-att-item"[^>]*>\s*<a\s+href="([^"]+)"/i', $html, $m)) {
            $hrefs = array_values(array_unique($m[1]));
            $nonInstruction = array_values(array_filter(
                $hrefs,
                fn (string $href) => ! $this->looksLikeInstructionFile($href),
            ));

            $chosen = $nonInstruction[0] ?? $hrefs[0];

            return $this->absoluteUrl(html_entity_decode($chosen), $baseUrl);
        }

        if (preg_match('/<a[^>]+href="([^"]*\.pdf[^"]*)"/i', $html, $m)) {
            return $this->absoluteUrl(html_entity_decode($m[1]), $baseUrl);
        }

        return null;
    }

    private function looksLikeInstructionFile(string $href): bool
    {
        $decoded = mb_strtolower(urldecode(html_entity_decode($href)));

        foreach (self::INSTRUCTION_FILE_HINTS as $hint) {
            if (str_contains($decoded, $hint)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function extractImageUrls(string $html, string $baseUrl, int $limit): array
    {
        $urls = [];

        if (preg_match_all('/class="vkwpb_img_wrap"><img[^>]+src="([^"]+)"/i', $html, $matches)) {
            $urls = $matches[1];
        }

        if (empty($urls) && preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $m)) {
            $urls[] = $m[1];
        }

        $urls = array_slice(array_values(array_unique($urls)), 0, max(1, $limit));

        return array_map(fn (string $u) => $this->absoluteUrl(html_entity_decode($u), $baseUrl), $urls);
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $base   = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host   = $base['host'] ?? '';
        $port   = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$port}{$href}";
        }

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');

        return "{$scheme}://{$host}{$port}{$basePath}/{$href}";
    }

    /**
     * @return array{0:UploadedFile,1:string} [dosya, temizlenecek geçici yol]
     */
    private function asUploadedFile(string $bytes, string $filename, string $mime): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'atelier_url_import_');
        file_put_contents($tmp, $bytes);

        return [new UploadedFile($tmp, $filename, $mime, null, true), $tmp];
    }

    private function filenameFromUrl(string $url, string $fallback): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $name = isset($query['file']) ? urldecode((string) $query['file']) : basename($path);
        $name = str_replace(['/', '\\'], '_', trim($name));

        return $name !== '' ? mb_substr($name, 0, 150) : $fallback;
    }
}
