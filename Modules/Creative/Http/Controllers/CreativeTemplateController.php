<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Http\Requests\StoreTemplateRequest;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\CreativeRenderService;
use Throwable;

class CreativeTemplateController extends Controller
{
    public function __construct(private CreativeRenderService $renderService) {}

    public function store(StoreTemplateRequest $request): RedirectResponse
    {
        $path = $request->file('svg')->store(
            'creative_templates',
            config('creative.disk', 'public'),
        );

        $template = CreativeTemplate::create([
            'name'      => $request->validated('name'),
            'svg_path'  => $path,
            'width'     => 0,
            'height'    => 0,
            'slots'     => [],
            'is_active' => true,
        ]);

        try {
            $this->renderService->inspectTemplate($template);
        } catch (Throwable $e) {
            Log::warning('Şablon incelenemedi', ['template_id' => $template->id, 'error' => $e->getMessage()]);

            return back()->with('warning', 'Şablon yüklendi ancak slotlar çıkarılamadı (Python/resvg kurulu mu?): ' . $e->getMessage());
        }

        return back()->with('success', 'Şablon yüklendi ve slotlar çıkarıldı.');
    }

    public function update(Request $request, CreativeTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'slots'     => ['sometimes', 'array'],
        ]);

        $template->update($data);

        return back()->with('success', 'Şablon güncellendi.');
    }

    public function destroy(CreativeTemplate $template): RedirectResponse
    {
        if ($template->svg_path) {
            Storage::disk(config('creative.disk', 'public'))->delete($template->svg_path);
        }

        $template->delete();

        return back()->with('success', 'Şablon silindi.');
    }
}
