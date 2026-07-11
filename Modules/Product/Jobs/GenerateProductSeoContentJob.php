<?php

namespace Modules\Product\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\Exceptions\AiGenerationException;

/**
 * Ürüne reçete (BOM) kaydedilince tetiklenir: AI ile SEO uyumlu başlık
 * (public_name) + storefront/bayi açıklamaları + meta alanlarını üretip ürüne yazar.
 *
 * Kuyruğa alınır çünkü Gemini çağrısı yavaştır (BOM kaydını bloklamamalı). AI
 * hatası siparişi/BOM'u etkilemez — loglanıp sessizce geçilir (idempotent retry).
 */
class GenerateProductSeoContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(public readonly int $productId) {}

    public function handle(ProductDescriptionGenerator $generator): void
    {
        $product = Product::with(['brand:id,name', 'category:id,name'])->find($this->productId);

        if ($product === null) {
            return;
        }

        try {
            $result = $generator->generate($product);
        } catch (AiGenerationException $e) {
            Log::warning('Ürün SEO içeriği üretilemedi (BOM tetikli).', [
                'product_id' => $this->productId,
                'error'      => $e->getMessage(),
            ]);

            return;
        }

        // AI'nin ürettiği alanları yaz; SEO alanları üretilmediyse mevcut değeri koru.
        $attributes = [
            'public_description' => $result->publicDescription,
            'tenant_description' => $result->tenantDescription,
            'ai_generated_at'    => now(),
        ];

        foreach ([
            'public_name'      => $result->publicName,
            'meta_title'       => $result->metaTitle,
            'meta_description' => $result->metaDescription,
            'meta_keywords'    => $result->metaKeywords,
        ] as $column => $value) {
            if (filled($value)) {
                $attributes[$column] = $value;
            }
        }

        $product->forceFill($attributes)->save();
    }
}
