<?php

namespace Modules\Tenant\Services\Marketplace\Trendyol;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Tenant\Models\TenantMarketplaceCredential;
use RuntimeException;

/**
 * Trendyol Partner API HTTP wrapper.
 *
 * Auth: Basic (supplier_id:api_key); secret api_secret User-Agent veya body imzasında.
 * Doc: https://developers.trendyol.com/ (Phase 3 dokümantasyon alt-task'ı detaylandıracak).
 */
class TrendyolHttpClient
{
    public function __construct(private TenantMarketplaceCredential $credential) {}

    public function http(): PendingRequest
    {
        $supplierId = $this->credential->supplier_id;
        $apiKey     = $this->credential->api_key;     // encrypted cast otomatik decrypt eder
        $apiSecret  = $this->credential->api_secret;

        if (! $supplierId || ! $apiKey || ! $apiSecret) {
            throw new RuntimeException('Trendyol credential eksik: supplier_id/api_key/api_secret zorunlu.');
        }

        return Http::baseUrl(config('tenant.marketplace.trendyol.base_url'))
            ->timeout((int) config('tenant.marketplace.trendyol.timeout', 30))
            ->withBasicAuth($apiKey, $apiSecret)
            ->withUserAgent($supplierId . ' - SelfIntegration')
            ->acceptJson()
            ->asJson();
    }

    /** Trendyol path'i absolute URL'ye çevirir (supplier_id placeholder'ı çözer). */
    public function path(string $template): string
    {
        return strtr($template, [
            '{supplierId}' => (string) $this->credential->supplier_id,
        ]);
    }

    public function supplierId(): string
    {
        return (string) $this->credential->supplier_id;
    }

    public function apiSecret(): string
    {
        return (string) $this->credential->api_secret;
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
