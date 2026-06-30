<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StoreMannequinRequest;
use Modules\Creative\Jobs\GenerateMannequinJob;
use Modules\Creative\Models\Mannequin;

class MannequinController extends Controller
{
    public function index(): Response
    {
        $mannequins = Mannequin::query()
            ->latest()
            ->get()
            ->map(fn (Mannequin $m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'gender'        => $m->gender,
                'age_range'     => $m->age_range,
                'skin_tone'     => $m->skin_tone,
                'body_type'     => $m->body_type,
                'height_cm'     => $m->height_cm,
                'bust_cm'       => $m->bust_cm,
                'waist_cm'      => $m->waist_cm,
                'hips_cm'       => $m->hips_cm,
                'status'        => $m->status,
                'error'         => $m->error,
                'reference_url' => $this->url($m->reference_image_path),
                'created_at'    => $m->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Creative::CreativeMannequins', [
            'mannequins' => $mannequins,
        ]);
    }

    public function store(StoreMannequinRequest $request): RedirectResponse
    {
        $mannequin = Mannequin::create(array_merge(
            $request->validated(),
            ['status' => Mannequin::STATUS_DRAFT],
        ));

        GenerateMannequinJob::dispatch($mannequin->id);

        return back()->with('success', 'Manken üretim kuyruğuna alındı.');
    }

    public function regenerate(Mannequin $mannequin): RedirectResponse
    {
        $mannequin->update(['status' => Mannequin::STATUS_DRAFT, 'error' => null]);

        GenerateMannequinJob::dispatch($mannequin->id);

        return back()->with('success', 'Manken yeniden üretim kuyruğuna alındı.');
    }

    public function destroy(Mannequin $mannequin): RedirectResponse
    {
        $mannequin->delete();

        return back()->with('success', 'Manken silindi.');
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
