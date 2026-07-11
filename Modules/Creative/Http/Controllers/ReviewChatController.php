<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Jobs\GenerateMannequinJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\ReviewChatService;

/**
 * Reddedilen manken/giydirme görselleri için AI destekli düzeltme sohbeti +
 * "talimatı uygula ve yeniden üret" aksiyonu.
 *
 * Route'lar 'creative.view' ile korunur (herkes okuyabilir); asıl daraltma
 * burada yapılır: sadece görselin ÜRETİCİSİ veya creative.approve sahibi bir
 * yönetici sohbet edebilir, ve sadece REDDEDİLMİŞ görseller için.
 */
class ReviewChatController extends Controller
{
    public function showMannequin(Mannequin $mannequin, ReviewChatService $chat): Response
    {
        $this->authorizeChat($mannequin);

        return $this->renderChatPage(
            subjectType: 'mannequin',
            subjectId: $mannequin->id,
            title: $mannequin->name,
            imageUrl: $mannequin->reference_image_path
                ? Storage::disk(config('creative.disk', 'public'))->url($mannequin->reference_image_path)
                : null,
            reviewNote: $mannequin->review_note,
            reviewTags: $mannequin->review_tags ?? [],
            chat: $chat,
            subject: $mannequin,
            backUrl: '/creative/mannequins',
        );
    }

    public function showTryon(TryonResult $result, ReviewChatService $chat): Response
    {
        $this->authorizeChat($result);
        $result->loadMissing('product:id,name');

        return $this->renderChatPage(
            subjectType: 'tryon',
            subjectId: $result->id,
            title: $result->product?->name ?? 'Ürün',
            imageUrl: Media::url($result->staged_image_path),
            reviewNote: $result->review_note,
            reviewTags: $result->review_tags ?? [],
            chat: $chat,
            subject: $result,
            backUrl: '/creative/tryon',
        );
    }

    private function renderChatPage(
        string $subjectType,
        int $subjectId,
        string $title,
        ?string $imageUrl,
        ?string $reviewNote,
        array $reviewTags,
        ReviewChatService $chat,
        Mannequin|TryonResult $subject,
        string $backUrl,
    ): Response {
        return Inertia::render('Creative::ReviewChat', [
            'subjectType' => $subjectType,
            'subjectId'   => $subjectId,
            'title'       => $title,
            'imageUrl'    => $imageUrl,
            'reviewNote'  => $reviewNote,
            'reviewTags'  => $reviewTags,
            'backUrl'     => $backUrl,
            'chats'       => $chat->history($subject)->map(fn ($c) => [
                'id'         => $c->id,
                'role'       => $c->role,
                'content'    => $c->content,
                'user_name'  => $c->user?->name,
                'created_at' => $c->created_at?->toDateTimeString(),
            ])->values(),
            'suggestion'  => $chat->latestSuggestion($subject),
        ]);
    }

    public function sendMannequin(Mannequin $mannequin, Request $request, ReviewChatService $chat): RedirectResponse
    {
        $this->authorizeChat($mannequin);

        $chat->converse($mannequin, auth()->user(), $this->validatedMessage($request));

        return back()->with('success', 'Mesaj gönderildi.');
    }

    public function sendTryon(TryonResult $result, Request $request, ReviewChatService $chat): RedirectResponse
    {
        $this->authorizeChat($result);

        $chat->converse($result, auth()->user(), $this->validatedMessage($request));

        return back()->with('success', 'Mesaj gönderildi.');
    }

    /**
     * Sohbetten çıkan en son düzeltme talimatını mankenin extras'ına ekler ve
     * kimlik görselini yeniden üretim kuyruğuna alır.
     */
    public function applyMannequin(Mannequin $mannequin, ReviewChatService $chat): RedirectResponse
    {
        $this->authorizeChat($mannequin);

        $suggestion = $chat->latestSuggestion($mannequin);
        if (! $suggestion) {
            return back()->with('error', 'Henüz uygulanabilir bir düzeltme talimatı yok; sohbete devam edin.');
        }

        $mannequin->update([
            'extras'        => trim(($mannequin->extras ?: '') . '. ' . $suggestion),
            'status'        => Mannequin::STATUS_DRAFT,
            'error'         => null,
            'review_status' => null,
            'review_note'   => null,
            'review_tags'   => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
        ]);

        GenerateMannequinJob::dispatch($mannequin->id);

        return redirect('/creative/mannequins')->with('success', 'Talimat uygulandı, manken yeniden üretim kuyruğuna alındı.');
    }

    /**
     * Sohbetten çıkan en son düzeltme talimatını sonucun meta'sına (ek talimat
     * olarak) ekler ve giydirmeyi yeniden üretim kuyruğuna alır.
     */
    public function applyTryon(TryonResult $result, ReviewChatService $chat): RedirectResponse
    {
        $this->authorizeChat($result);

        $suggestion = $chat->latestSuggestion($result);
        if (! $suggestion) {
            return back()->with('error', 'Henüz uygulanabilir bir düzeltme talimatı yok; sohbete devam edin.');
        }

        $result->update([
            'meta'          => array_merge($result->meta ?? [], ['extra_instructions' => $suggestion]),
            'status'        => TryonResult::STATUS_QUEUED,
            'error'         => null,
            'review_status' => null,
            'review_note'   => null,
            'review_tags'   => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
        ]);

        GenerateOnModelJob::dispatch($result->id);

        return redirect('/creative/tryon')->with('success', 'Talimat uygulandı, görsel yeniden üretim kuyruğuna alındı.');
    }

    private function authorizeChat(Mannequin|TryonResult $subject): void
    {
        $user       = auth()->user();
        $isCreator  = $subject->created_by !== null && $subject->created_by === $user->id;
        $isReviewer = $user->can('creative.approve');

        abort_unless($isCreator || $isReviewer, 403, 'Bu sohbete erişim yetkiniz yok.');

        if ($subject->review_status !== $subject::REVIEW_REJECTED) {
            $back = $subject instanceof Mannequin ? '/creative/mannequins' : '/creative/tryon';
            abort(redirect($back)->with('error', 'Görselin durumu değiştiği için sohbet kullanılamıyor.'));
        }
    }

    private function validatedMessage(Request $request): string
    {
        return $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ])['message'];
    }
}
