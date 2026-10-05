<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * GeminiClient hatası. Mesaj kullanıcıya gösterilebilir (creative::messages.ai_provider.<reason>);
 * Google'ın ham cevabı mesaja girmez, GeminiClient onu ayrıca loglar.
 * RuntimeException'dan türer: mevcut `catch (RuntimeException)` / `catch (Throwable)` noktaları aynen çalışır.
 */
class GeminiRequestException extends RuntimeException
{
    public const MISSING_KEY = 'missing_key';
    public const AUTH = 'auth';                 // anahtar geçersiz
    public const DENIED = 'denied';             // proje/bölge erişimi reddedildi (403, FAILED_PRECONDITION)
    public const QUOTA = 'quota';               // 429 RESOURCE_EXHAUSTED
    public const UNAVAILABLE = 'unavailable';   // 5xx, aşırı yük
    public const UNREACHABLE = 'unreachable';   // bağlantı/zaman aşımı
    public const BAD_REQUEST = 'bad_request';   // 400 (prompt/parametre)
    public const BLOCKED = 'blocked';           // güvenlik filtresi yanıtı kesti
    public const EMPTY_RESPONSE = 'empty_response';
    public const FILE_MISSING = 'file_missing';
    public const FAILED = 'failed';

    public function __construct(
        public readonly string $reason,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(__('creative::messages.ai_provider.'.$reason), $httpStatus ?? 0, $previous);
    }

    /** HTTP hata cevabını sebebe çevirir (Google hata gövdesi: error.{code,status,message}). */
    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $providerStatus = $response->json('error.status');
        $message = strtolower((string) $response->json('error.message'));

        $reason = match (true) {
            $status === 401, $providerStatus === 'UNAUTHENTICATED',
            str_contains($message, 'api key not valid'), str_contains($message, 'api_key_invalid') => self::AUTH,
            $status === 403, $providerStatus === 'PERMISSION_DENIED', $providerStatus === 'FAILED_PRECONDITION' => self::DENIED,
            $status === 429, $providerStatus === 'RESOURCE_EXHAUSTED' => self::QUOTA,
            $status >= 500 => self::UNAVAILABLE,
            $status === 400 => self::BAD_REQUEST,
            default => self::FAILED,
        };

        return new self($reason, $status, is_string($providerStatus) ? $providerStatus : null);
    }
}
