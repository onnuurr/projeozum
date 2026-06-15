<?php

namespace App\Logging;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Log;

/**
 * Uygulama hata kayıtçısı.
 *
 * exception.report callback'ından veya doğrudan çağrılır:
 *   app(ErrorLogger::class)->capture($throwable, ['extra' => 'context']);
 *
 * İçeriden fırlayan her türlü hata sessizce yutulur (loglama asla exception fırlatmamalı).
 */
class ErrorLogger
{
    public function __construct(
        private readonly LogSanitizer $sanitizer,
    ) {}

    /**
     * İstisna yakalar, ErrorLog satırı yazar ve hatayı dosyaya iletir.
     */
    public function capture(\Throwable $e, array $context = []): void
    {
        try {
            // ── Parmak izi ────────────────────────────────────────────────────
            $fingerprint = sha1(get_class($e) . ':' . $e->getFile() . ':' . $e->getLine());

            // ── Modül çıkarımı ────────────────────────────────────────────────
            $module = $this->inferModule($e);

            // ── IP / URL / method (console bağlamında null olabilir) ───────────
            $ipAddress = null;
            $url       = null;
            $method    = null;

            try {
                $req       = request();
                $ipAddress = $req->ip();
                $url       = $req->fullUrl();
                $method    = $req->method();
            } catch (\Throwable) {
                // Console / queue bağlamı — request() yoksa atla
            }

            // ── Causer ────────────────────────────────────────────────────────
            $causerId = null;
            try {
                $causerId = auth()->id();
            } catch (\Throwable) {
                // Auth guard başlatılmamışsa sessiz geç
            }

            // ── DB kaydı ──────────────────────────────────────────────────────
            ErrorLog::create([
                'module'          => $module,
                'level'           => 'error',
                'message'         => $e->getMessage(),
                'exception_class' => get_class($e),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
                'trace'           => $e->getTraceAsString(),
                'url'             => $url,
                'method'          => $method,
                'causer_id'       => $causerId,
                'ip_address'      => $ipAddress,
                'context'         => $this->sanitizer->sanitize($context),
                'fingerprint'     => $fingerprint,
                'occurred_at'     => now(),
            ]);

            // ── Dosya kanalı ──────────────────────────────────────────────────
            Log::channel('errors')->error($e->getMessage(), [
                'module' => $module,
                'file'   => $e->getFile() . ':' . $e->getLine(),
            ]);

        } catch (\Throwable) {
            // Loglama asla exception fırlatmaz — her şeyi yut.
        }
    }

    // -------------------------------------------------------------------------
    // Özel yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Exception'ın dosya yolundan veya trace namespace'inden modülü çıkarır.
     * Modules/<Name>/ yolunu arar.
     */
    private function inferModule(\Throwable $e): ?string
    {
        // Önce exception'ın kendi dosyasına bak
        if (preg_match('#[/\\\\]Modules[/\\\\]([^/\\\\]+)[/\\\\]#', $e->getFile(), $m)) {
            return strtolower($m[1]);
        }

        // Stack trace'den ilk Modules/<Name> frame'i bul
        foreach ($e->getTrace() as $frame) {
            $file  = $frame['file'] ?? '';
            $class = $frame['class'] ?? '';

            if ($file !== '' && preg_match('#[/\\\\]Modules[/\\\\]([^/\\\\]+)[/\\\\]#', $file, $m)) {
                return strtolower($m[1]);
            }

            if ($class !== '' && preg_match('/^Modules\\\\([^\\\\]+)\\\\/i', $class, $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }
}
