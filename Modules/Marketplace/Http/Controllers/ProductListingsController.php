<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Product;

class ProductListingsController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'color', 'logo_text', 'connected'])
            ->map(fn (Marketplace $m) => [
                'key'       => $m->key,
                'name'      => $m->name,
                'color'     => $m->color,
                'logoText'  => $m->logo_text,
                'connected' => $m->connected,
            ]);

        return Inertia::render('Marketplace::ProductListings', [
            'marketplaces' => $marketplaces,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]);

        return response()->json(['data' => $products]);
    }
}
