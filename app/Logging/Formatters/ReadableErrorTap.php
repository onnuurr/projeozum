<?php

namespace App\Logging\Formatters;

use Monolog\Formatter\LineFormatter;

/**
 * Laravel logging "tap" sınıfı.
 *
 * config/logging.php'de `tap` anahtarına eklenerek hata kanalına
 * okunabilir tek-satır + trace formatı uygular.
 *
 * Format: [2026-06-15 14:30:00] ERROR module=Product file.php:42 — Mesaj
 */
class ReadableErrorTap
{
    public function __invoke($logger): void
    {
        $format = "[%datetime%] %level_name% %message% %context%\n%extra%\n";

        $formatter = new LineFormatter(
            format: $format,
            dateFormat: 'Y-m-d H:i:s',
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true,
        );

        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'setFormatter')) {
                $handler->setFormatter($formatter);
            }
        }
    }
}
