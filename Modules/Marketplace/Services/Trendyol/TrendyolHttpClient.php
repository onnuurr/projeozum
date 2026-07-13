<?php

namespace Modules\Marketplace\Services\Trendyol;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Marketplace\DTOs\MarketplaceCredentials;
use RuntimeException;

/**
 * Trendyol Partner API HTTP wrapper.
 *
 * Auth: Basic (supplierId:apiKey); secret apiSecret User-Agent veya body imzasında.
 * Doc: https://developers.trendyol.com/ (Phase 3 dokümantasyon alt-task'ı detaylandıracak).
 */
class TrendyolHttpClient
{
    public function __construct(private MarketplaceCredentials $credentials) {}

    public function http(): PendingRequest
    {
        $supplierId = $this->credentials->accountId;
        $apiKey     = $this->credentials->apiKey;
        $apiSecret  = $this->credentials->apiSecret;

        if (! $supplierId || ! $apiKey || ! $apiSecret) {
            throw new RuntimeException('Trendyol credential eksik: supplierId/apiKey/apiSecret zorunlu.');
        }

        return Http::baseUrl(config('marketplace.trendyol.base_url'))
            ->timeout((int) config('marketplace.trendyol.timeout', 30))
            ->withBasicAuth($apiKey, $apiSecret)
            ->withUserAgent($supplierId . ' - SelfIntegration')
            ->acceptJson()
            ->asJson();
    }

    /** Trendyol path'i absolute URL'ye çevirir (supplierId placeholder'ı çözer). */
    public function path(string $template): string
    {
        return strtr($template, [
            '{supplierId}' => (string) $this->credentials->accountId,
        ]);
    }

    public function supplierId(): string
    {
        return (string) $this->credentials->accountId;
    }

    public function apiSecret(): string
    {
        return (string) $this->credentials->apiSecret;
    }

    /**
     * Trendyol response hata sözleşmesi — 4xx/5xx → exception fırlat.
     */
    public function ensureSuccess(Response $response, string $context): Response
    {
        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('Trendyol %s başarısız (HTTP %d): %s', $context, $response->status(), $response->body()),
            );
        }

        return $response;
    }
}
