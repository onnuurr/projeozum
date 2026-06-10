<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StoreTemplateRequest;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\CreativeRenderService;
use Throwable;

class CreativeTemplateController extends Controller
{
    public function __construct(private CreativeRenderService $renderService) {}

    public function index(): Response
    {
        $templates = CreativeTemplate::query()
            ->latest()
            ->get(['id', 'name', 'width', 'height', 'svg_path', 'thumbnail_path', 'slots', 'is_active'])
            ->map(fn (CreativeTemplate $t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'width'      => $t->width,
                'height'     => $t->height,
                'slots'      => $t->slots ?? [],
                'is_active'  => $t->is_active,
                'svg_url'    => $this->url($t->svg_path),
                'preview_url' => $this->url($t->thumbnail_path ?: $t->svg_path),
            ]);

        return Inertia::render('Creative::CreativeTemplates', [
            'templates' => $templates,
        ]);
    }

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

    /**
     * Tasarımcıdan gelen slot konum/özelliklerini SVG'ye yazıp DB'yi tazeler.
     */
    public function updateSlots(Request $request, CreativeTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'slots'             => ['present', 'array'],
            'slots.*.ref'       => ['nullable', 'string', 'max:191'],
            'slots.*.key'       => ['required', 'string', 'max:191'],
            'slots.*.type'      => ['required', 'in:text,image'],
            'slots.*.x'         => ['required', 'numeric'],
            'slots.*.y'         => ['required', 'numeric'],
            'slots.*.w'         => ['nullable', 'numeric'],
            'slots.*.h'         => ['nullable', 'numeric'],
            'slots.*.fit'       => ['nullable', 'in:cover,contain'],
            'slots.*.font_size' => ['nullable', 'numeric'],
            'slots.*.bold'      => ['nullable', 'boolean'],
            'slots.*.align'     => ['nullable', 'in:left,center,right'],
            'slots.*.fill'      => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->renderService->applyTemplateSlots($template, $data['slots']);
        } catch (Throwable $e) {
            Log::warning('Şablon slotları uygulanamadı', ['template_id' => $template->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Slotlar kaydedilemedi (Python kurulu mu?): ' . $e->getMessage());
        }

        return back()->with('success', 'Şablon slotları kaydedildi.');
    }

    public function destroy(CreativeTemplate $template): RedirectResponse
    {
        if ($template->svg_path) {
            Storage::disk(config('creative.disk', 'public'))->delete($template->svg_path);
        }

        $template->delete();

        return back()->with('success', 'Şablon silindi.');
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
