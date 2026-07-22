<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Product\Events\AiAnalysisCompleted;
use Modules\Product\Http\Requests\GenerateProductDescriptionRequest;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\Exceptions\AiGenerationException;
use Modules\Tenant\Models\Tenant;

class ProductAiDescriptionController extends Controller
{
    public function __construct(private ProductDescriptionGenerator $generator) {}

    public function generate(GenerateProductDescriptionRequest $request, Product $product): JsonResponse
    {
        $tenantId = $request->validated()['tenant_id'] ?? null;
        $tenant   = $tenantId ? Tenant::find($tenantId) : null;

        $product->loadMissing(['brand:id,name', 'category:id,name']);

        try {
            $result = $this->generator->generate($product, $tenant);
        } catch (AiGenerationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        // Sadece "son AI ne zaman koştu" audit'i için timestamp güncelle;
        // metni burada persist ETMEYİZ — admin edit edip form üzerinden kaydeder.
        $product->forceFill(['ai_generated_at' => now()])->save();

        AiAnalysisCompleted::dispatch($product);

        return response()->json([
            'data' => [
                'public_description' => $result->publicDescription,
                'tenant_description' => $result->tenantDescription,
                'model'              => $result->model,
                'tenant_id'          => $tenant?->id,
            ],
        ]);
    }
}
