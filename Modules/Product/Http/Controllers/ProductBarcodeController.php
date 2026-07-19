<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Product\Exceptions\BarcodeGenerationException;
use Modules\Product\Services\ProductService;

class ProductBarcodeController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function generate(): JsonResponse
    {
        try {
            $barcode = $this->products->generateBarcode();
        } catch (BarcodeGenerationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'barcode' => $barcode,
        ]);
    }
}
