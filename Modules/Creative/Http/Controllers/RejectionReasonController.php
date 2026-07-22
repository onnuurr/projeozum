<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StoreRejectionReasonRequest;
use Modules\Creative\Http\Requests\UpdateRejectionReasonRequest;
use Modules\Creative\Models\RejectionReason;

/**
 * Ret ekranındaki "düzeltilmesi gereken alan" seçim maddelerinin yönetimi.
 *
 * Superadmin panelinden erişilir; route'lar 'creative.rejection-reasons.manage'
 * izniyle korunur (yalnız superadmin'e verilir, Gate::before ile geçer). Buradaki
 * maddeler reddetme diyaloğunda checkbox olarak çıkar ve seçimler chatbot'a
 * otomatik aktarılır (bkz. ReviewChatService).
 */
class RejectionReasonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Creative::CreativeRejectionReasons', [
            'reasons' => RejectionReason::query()
                ->ordered()
                ->get(['id', 'category', 'context', 'label', 'hint', 'sort_order', 'is_active']),
            'contexts' => RejectionReason::CONTEXTS,
        ]);
    }

    public function store(StoreRejectionReasonRequest $request): RedirectResponse
    {
        RejectionReason::create($request->validated());

        return back()->with('success', 'Ret seçim maddesi eklendi.');
    }

    public function update(UpdateRejectionReasonRequest $request, RejectionReason $rejectionReason): RedirectResponse
    {
        $rejectionReason->update($request->validated());

        return back()->with('success', 'Ret seçim maddesi güncellendi.');
    }

    public function destroy(RejectionReason $rejectionReason): RedirectResponse
    {
        // SoftDeletes: geçmiş retlerdeki etiket snapshot'ları korunur, işlem geri alınabilir.
        $rejectionReason->delete();

        return back()->with('success', 'Ret seçim maddesi kaldırıldı.');
    }
}
