<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StorePoseRequest;
use Modules\Creative\Jobs\GeneratePosePreviewJob;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\PoseService;

class PoseController extends Controller
{
    public function index(): Response
    {
        $poses = Pose::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Pose $p) => [
                'id'          => $p->id,
                'pose_key'    => $p->pose_key,
                'label'       => $p->label,
                'status'      => $p->status,
                'error'       => $p->error,
                'preview_url' => $this->url($p->preview_image_path),
            ]);

        return Inertia::render('Creative::CreativePoses', [
            'poses'         => $poses,
            'catalog_count' => count((array) config('creative.mannequin.poses', [])),
        ]);
    }

    /**
     * Katalogdaki pozları kütüphaneye işler ve önizlemelerini kuyruğa alır.
     */
    public function generateAll(PoseService $poses): RedirectResponse
    {
        $seeded = $poses->seed();
        foreach ($seeded as $pose) {
            GeneratePosePreviewJob::dispatch($pose->id);
        }

        return back()->with('success', $seeded->count() . ' poz önizlemesi üretim kuyruğuna alındı.');
    }

    /**
     * Operatör tanımlı özel poz ekler ve önizlemesini kuyruğa alır.
     */
    public function store(StorePoseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $pose = Pose::create([
            'pose_key'   => $this->uniqueKey($data['label']),
            'label'      => $data['label'],
            'prompt'     => $data['prompt'],
            'sort_order' => (int) Pose::max('sort_order') + 1,
            'status'     => Pose::STATUS_DRAFT,
        ]);

        GeneratePosePreviewJob::dispatch($pose->id);

        return back()->with('success', 'Poz eklendi, önizleme kuyruğa alındı.');
    }

    public function regenerate(Pose $pose): RedirectResponse
    {
        $pose->update(['status' => Pose::STATUS_DRAFT, 'error' => null]);

        GeneratePosePreviewJob::dispatch($pose->id);

        return back()->with('success', 'Poz önizlemesi yeniden üretim kuyruğuna alındı.');
    }

    public function destroy(Pose $pose): RedirectResponse
    {
        if ($pose->preview_image_path) {
            Storage::disk(config('creative.disk', 'public'))->delete($pose->preview_image_path);
        }

        $pose->delete();

        return back()->with('success', 'Poz silindi.');
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'pose';
        $key  = $base;
        $i    = 1;
        while (Pose::where('pose_key', $key)->exists()) {
            $key = $base . '_' . (++$i);
        }

        return Str::limit($key, 60, '');
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
