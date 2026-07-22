<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Controllers\Concerns\HandlesCreativeReview;
use Modules\Creative\Http\Requests\StoreMannequinRequest;
use Modules\Creative\Jobs\GenerateMannequinJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Services\ReviewNotifier;

class MannequinController extends Controller
{
    use HandlesCreativeReview;

    public function index(): Response
    {
        $mannequins = Mannequin::query()
            ->with([
                'creator:id,name',
                'reviewer:id,name',
                'reviewChats' => fn ($q) => $q->orderBy('created_at')->with('user:id,name'),
            ])
            ->latest()
            ->get()
            ->map(fn (Mannequin $m) => [
                'id'              => $m->id,
                'name'            => $m->name,
                'gender'          => $m->gender,
                'age_range'       => $m->age_range,
                'skin_tone'       => $m->skin_tone,
                'body_type'       => $m->body_type,
                'height_cm'       => $m->height_cm,
                'bust_cm'         => $m->bust_cm,
                'waist_cm'        => $m->waist_cm,
                'hips_cm'         => $m->hips_cm,
                'status'          => $m->status,
                'error'           => $m->error,
                'reference_url'   => $this->url($m->reference_image_path, $m->updated_at?->timestamp),
                'source_photo_url' => $this->url($m->source_photo_path),
                'created_at'      => $m->created_at?->toDateTimeString(),
                'created_by'      => $m->created_by,
                'creator_name'    => $m->creator?->name,
                'review_status'   => $m->review_status,
                'review_note'     => $m->review_note,
                'review_tags'     => $m->review_tags ?? [],
                'reviewer_name'   => $m->reviewer?->name,
                'can_review'      => auth()->user()?->can('creative.approve')
                    && $m->created_by !== auth()->id()
                    && $m->review_status === Mannequin::REVIEW_PENDING,
                'is_own'          => $m->created_by === auth()->id(),
                'can_chat'        => $m->review_status === Mannequin::REVIEW_REJECTED
                    && ($m->created_by === auth()->id() || auth()->user()?->can('creative.approve')),
                'review_chats'    => $m->reviewChats->map(fn ($c) => [
                    'id'         => $c->id,
                    'role'       => $c->role,
                    'content'    => $c->content,
                    'user_name'  => $c->user?->name,
                    'created_at' => $c->created_at?->toDateTimeString(),
                ]),
                'chat_suggestion' => $m->meta['chat_suggested_instruction'] ?? null,
            ]);

        return Inertia::render('Creative::CreativeMannequins', [
            'mannequins'       => $mannequins,
            'rejectionReasons' => $this->rejectionReasonGroups('mannequin'),
        ]);
    }

    public function store(StoreMannequinRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('reference_photo');

        if ($request->hasFile('reference_photo')) {
            $data['source_photo_path'] = $request->file('reference_photo')
                ->store('creative/mannequin_references', config('creative.disk', 'public'));
        }

        $mannequin = Mannequin::create(array_merge(
            $data,
            ['status' => Mannequin::STATUS_DRAFT, 'created_by' => auth()->id()],
        ));

        GenerateMannequinJob::dispatch($mannequin->id);

        return back()->with('success', 'Manken üretim kuyruğuna alındı.');
    }

    public function regenerate(Mannequin $mannequin): RedirectResponse
    {
        $mannequin->update([
            'status'        => Mannequin::STATUS_DRAFT,
            'error'         => null,
            'review_status' => null,
            'review_note'   => null,
            'review_tags'   => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
        ]);

        GenerateMannequinJob::dispatch($mannequin->id);

        return back()->with('success', 'Manken yeniden üretim kuyruğuna alındı.');
    }

    public function approve(Mannequin $mannequin, ReviewNotifier $notifier): RedirectResponse
    {
        $this->guardNotOwnWork($mannequin);

        if ($mannequin->review_status !== Mannequin::REVIEW_PENDING) {
            return back()->with('error', 'Bu manken onay bekliyor durumda değil.');
        }

        $mannequin->update([
            'review_status' => Mannequin::REVIEW_APPROVED,
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($mannequin, true);

        return back()->with('success', 'Manken onaylandı.');
    }

    public function reject(Mannequin $mannequin, Request $request, ReviewNotifier $notifier): RedirectResponse
    {
        $this->guardNotOwnWork($mannequin);

        if ($mannequin->review_status !== Mannequin::REVIEW_PENDING) {
            return back()->with('error', 'Bu manken onay bekliyor durumda değil.');
        }

        $review = $this->validatedReview($request);

        $mannequin->update([
            'review_status' => Mannequin::REVIEW_REJECTED,
            'review_note'   => $review['note'],
            'review_tags'   => $review['tags'],
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($mannequin, false);

        return back()->with('success', 'Manken reddedildi.');
    }

    public function destroy(Mannequin $mannequin): RedirectResponse
    {
        $mannequin->delete();

        return back()->with('success', 'Manken silindi.');
    }

    private function url(?string $path, ?int $version = null): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk(config('creative.disk', 'public'))->url($path);

        // Nginx bu dosyaları `expires max` ile önbelleğe alıyor; regenerate sonrası
        // aynı dosya adı (reference.png) üzerine yazıldığı için sürüm parametresi
        // olmadan tarayıcı/CDN eski görseli göstermeye devam eder.
        return $version ? "{$url}?v={$version}" : $url;
    }
}
