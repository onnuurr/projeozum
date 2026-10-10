<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\Creative\Http\Requests\RerunMockupRequest;
use Modules\Creative\Http\Requests\StartBulkMockupRequest;
use Modules\Creative\Http\Requests\StartMockupRequest;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupProgress;
use Modules\Creative\Services\Mockup\MockupRerunService;
use Modules\Product\Models\Product;
use RuntimeException;

/**
 * Ürün Giydirme sayfasından mokap başlatma (Faz 4). Üretim kuyrukta (Batch API) yürür, sonuçlar
 * aynı onay listesine düşer; hiçbir şey onaysız yayınlanmaz. Günlük limit + varsayılan manken
 * MockupSettings'te (Creative ayarlar › Mokap).
 */
class MockupController extends Controller
{
    public function store(StartMockupRequest $request, MockupPipelineService $pipeline): RedirectResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));
        $mannequin = $request->validated('mannequin_id') ? Mannequin::find($request->validated('mannequin_id')) : null;

        try {
            $started = $pipeline->startFor($product, $mannequin, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $started === []
            ? __('creative::messages.mockup.colors_queued', ['product' => $product->name])
            : __('creative::messages.mockup.groups_queued', ['product' => $product->name, 'count' => count($started)]));
    }

    /** Faz 5: reddedilen mokap sonucunu ret nedeninin aşamasından düzeltme talimatıyla yeniden üretir. */
    public function rerun(TryonResult $result, RerunMockupRequest $request, MockupRerunService $rerun): RedirectResponse
    {
        try {
            $plan = $rerun->rerun($result);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('creative::messages.mockup.rerun_queued', ['stage' => $plan['label']]));
    }

    /** Arka planda çalışan (ve son birkaç dakikada biten) mokapların aşaması — sayfa bunu yoklar. */
    public function progress(MockupProgress $progress): JsonResponse
    {
        return response()->json(['items' => $progress->active()]);
    }

    public function bulk(StartBulkMockupRequest $request, MockupPipelineService $pipeline): RedirectResponse
    {
        $result = $pipeline->startBulk((int) $request->validated('limit'), $request->user()->id);

        return $result['started'] > 0
            ? back()->with('success', __('creative::messages.mockup.bulk_queued', ['count' => $result['started']]))
            : back()->with('error', __('creative::messages.mockup.bulk_none'));
    }
}
