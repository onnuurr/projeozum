<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Product\Events\AiAnalysisCompleted;
use Modules\Product\Http\Requests\GenerateProductContentRequest;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\Exceptions\AiGenerationException;
use Modules\Product\Services\Ai\ProductContentDraft;
use Modules\Product\Services\Ai\PromptMaterialResolver;
use Modules\Tenant\Models\Tenant;

/**
 * Ürün formundaki AI butonları (açıklama + SEO). Kayıt beklemez: formda o an yazılı bilgilerden
 * kaydedilmeyen bir taslak ürün kurulur ve onunla üretilir. Metin burada persist EDİLMEZ —
 * admin gözden geçirip form üzerinden kaydeder.
 */
class ProductAiDescriptionController extends Controller
{
    public function __construct(
        private ProductDescriptionGenerator $generator,
        private ProductContentDraft $drafts,
        private PromptMaterialResolver $materials,
    ) {}

    public function generate(GenerateProductContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = isset($data['product_id']) ? Product::find($data['product_id']) : null;
        $tenant = isset($data['tenant_id']) ? Tenant::find($data['tenant_id']) : null;

        $draft = $this->drafts->make($data, $product);
        $materialLines = $this->materials->forDraft($draft, $data['description_materials'] ?? []);

        try {
            $result = $this->generator->generate($draft, $tenant, array_values(array_unique($data['parts'])), $materialLines);
        } catch (AiGenerationException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // Kayıtlı üründe yalnız "son AI ne zaman koştu" audit'i (+ zaman tüneli).
        if ($product) {
            $product->forceFill(['ai_generated_at' => now()])->save();
            AiAnalysisCompleted::dispatch($product);
        }

        return response()->json([
            'data' => [...$result->toArray(), 'tenant_id' => $tenant?->id],
        ]);
    }
}
