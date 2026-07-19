<?php

namespace Modules\Superadmin\Services;

use Illuminate\Support\Facades\Http;
use Modules\Superadmin\Models\Setting;
use Throwable;

class CloudflarePurgeService
{
    /**
     * Cloudflare zone'undaki tüm önbelleği temizler. Zone ID / API Token
     * Superadmin > Ayarlar > API sekmesinde ("api" grubu) saklanır.
     */
    public function purgeAll(): array
    {
        $creds  = Setting::getGroup('api');
        $zoneId = trim((string) ($creds['cloudflareZoneId'] ?? ''));
        $token  = trim((string) ($creds['cloudflareApiToken'] ?? ''));

        if ($zoneId === '' || $token === '') {
            return ['ok' => false, 'message' => 'Cloudflare Zone ID / API Token tanımlı değil (Ayarlar > API).'];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                    'purge_everything' => true,
                ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Cloudflare API\'ye ulaşılamadı: ' . $e->getMessage()];
        }

        if ($response->successful() && $response->json('success') === true) {
            return ['ok' => true, 'message' => 'Cloudflare önbelleği temizlendi.'];
        }

        $errors = collect($response->json('errors') ?? [])->pluck('message')->implode(', ');

        return ['ok' => false, 'message' => $errors !== '' ? "Cloudflare: {$errors}" : 'Cloudflare API hatası (' . $response->status() . ').'];
    }
}
