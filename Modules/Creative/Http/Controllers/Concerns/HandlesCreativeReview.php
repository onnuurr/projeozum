<?php

namespace Modules\Creative\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\RejectionReason;
use Modules\Creative\Models\TryonResult;

/**
 * MannequinController, TryonController ve CreativeStudioController'ın onay/ret
 * aksiyonlarında paylaştığı yetkilendirme ve validasyon mantığı. Route zaten
 * 'can:creative.approve' ile korunur; burada EK olarak "üretici kendi işini
 * onaylayamaz/reddedemez" kuralı uygulanır (route middleware bunu tek başına
 * ifade edemez).
 */
trait HandlesCreativeReview
{
    private function guardNotOwnWork(Mannequin|TryonResult|CreativeAsset $subject): void
    {
        if ($subject->created_by !== null && $subject->created_by === auth()->id()) {
            abort(403, 'Kendi ürettiğiniz görseli onaylayamaz/reddedemezsiniz.');
        }
    }

    /**
     * Ret gerekçesi: serbest açıklama + "düzeltilmesi gereken alan" seçim maddeleri.
     * En az biri gerekli — yönetici ya bir açıklama yazar ya da hazır madde işaretler
     * (ideali ikisi birden). Seçimler etiket snapshot'ı olarak saklanır.
     *
     * @return array{note: ?string, tags: array<int,string>}
     */
    private function validatedReview(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'reason'   => ['nullable', 'string', 'max:2000'],
            'tags'     => ['nullable', 'array', 'max:30'],
            'tags.*'   => ['string', 'max:120'],
        ])->after(function ($validator) use ($request) {
            $reason = trim((string) $request->input('reason', ''));
            $tags   = array_filter((array) $request->input('tags', []));

            if ($tags === [] && mb_strlen($reason) < 10) {
                $validator->errors()->add(
                    'reason',
                    'En az bir düzeltme alanı seçin veya en az 10 karakterlik açıklama yazın.',
                );
            }
        })->validate();

        // Etiketleri normalize et: kırp, boşları at, tekilleştir, sırayı koru.
        $tags = array_values(array_unique(array_filter(
            array_map(fn ($t) => trim((string) $t), $data['tags'] ?? []),
        )));

        $note = isset($data['reason']) ? trim($data['reason']) : '';

        return ['note' => $note !== '' ? $note : null, 'tags' => $tags];
    }

    /**
     * Ret ekranındaki hazır seçim maddeleri, kategoriye göre gruplanmış hâlde.
     * Superadmin panelinden yönetilir; yalnız aktif ve bu ekranın context'ine
     * (veya context'i NULL olan "tüm ekranlar" maddelerine) ait olanlar döner.
     *
     * @param  'gallery'|'mannequin'|'tryon'  $context
     * @return array<int, array{category: string, items: array<int, string>}>
     */
    private function rejectionReasonGroups(string $context): array
    {
        return RejectionReason::query()
            ->active()
            ->forContext($context)
            ->ordered()
            ->get(['category', 'label'])
            ->groupBy('category')
            ->map(fn ($rows, $category) => [
                'category' => $category,
                'items'    => $rows->pluck('label')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
