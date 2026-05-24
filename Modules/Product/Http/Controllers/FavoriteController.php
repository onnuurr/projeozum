<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductFavorite;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $userId = $request->user()->id;

        $existing = ProductFavorite::query()
            ->where('user_id', $userId)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ProductFavorite::create([
                'user_id'    => $userId,
                'product_id' => $product->id,
            ]);
        }

        return back();
    }
}
