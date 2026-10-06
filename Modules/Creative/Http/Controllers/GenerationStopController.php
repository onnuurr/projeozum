<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Creative\Http\Requests\StopGenerationRequest;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\GenerationStopService;

/** Ürün Giydirme › arka planda süren görsel üretimlerini durdurma (bkz. GenerationStopService). */
class GenerationStopController extends Controller
{
    public function result(TryonResult $result, StopGenerationRequest $request, GenerationStopService $stop): RedirectResponse
    {
        return $this->respond($stop->stopResult($result));
    }

    public function many(StopGenerationRequest $request, GenerationStopService $stop): RedirectResponse
    {
        $productId = $request->validated('product_id');

        return $this->respond($productId ? $stop->stopProduct((int) $productId) : $stop->stopAll());
    }

    /** @param array{results:int, outfits:int, batch:int} $done */
    private function respond(array $done): RedirectResponse
    {
        if ($done['results'] === 0 && $done['outfits'] === 0) {
            return back()->with('error', __('creative::messages.stop.nothing'));
        }

        return back()->with('success', __('creative::messages.stop.done', $done));
    }
}
