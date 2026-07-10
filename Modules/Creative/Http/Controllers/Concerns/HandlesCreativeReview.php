<?php

namespace Modules\Creative\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

/**
 * MannequinController ve TryonController'ın onay/ret aksiyonlarında paylaştığı
 * yetkilendirme ve validasyon mantığı. Route zaten 'can:creative.approve' ile
 * korunur; burada EK olarak "üretici kendi işini onaylayamaz/reddedemez" kuralı
 * uygulanır (route middleware bunu tek başına ifade edemez).
 */
trait HandlesCreativeReview
{
    private function guardNotOwnWork(Mannequin|TryonResult $subject): void
    {
        if ($subject->created_by !== null && $subject->created_by === auth()->id()) {
            abort(403, 'Kendi ürettiğiniz görseli onaylayamaz/reddedemezsiniz.');
        }
    }

    private function validatedReviewNote(Request $request): string
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ])['reason'];
    }
}
