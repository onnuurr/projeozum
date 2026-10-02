<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StorePartnerBrandKitRequest;
use Modules\Creative\Models\PartnerBrandKit;

/**
 * İş ortağı (partner) kreatif bilgileri — partner_share kanalında BrandKit'e
 * EK olarak Creative Rules Engine'e beslenir (bkz. CreativeRulesEngine,
 * CreativeRenderService::buildCreativeSpec).
 */
class PartnerBrandKitController extends Controller
{
    public function index(): Response
    {
        $kits = PartnerBrandKit::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (PartnerBrandKit $k) => [
                'id'            => $k->id,
                'name'          => $k->name,
                'is_active'     => $k->is_active,
                'logo_path'     => $k->logo_path,
                'logo_url'      => $k->logo_path ? Storage::disk($this->disk())->url($k->logo_path) : null,
                'contact'       => (object) ($k->contact ?? []),
                'cta_phrases'   => $k->cta_phrases ?? [],
                'price_display' => (object) ($k->price_display ?? []),
                'notes'         => $k->notes,
            ]);

        return Inertia::render('Creative::PartnerBrandKits', [
            'kits' => $kits,
        ]);
    }

    public function store(StorePartnerBrandKitRequest $request): RedirectResponse
    {
        PartnerBrandKit::create($this->payload($request));

        return back()->with('success', __('creative::messages.partner_brand_kit.created'));
    }

    public function update(StorePartnerBrandKitRequest $request, PartnerBrandKit $partnerBrandKit): RedirectResponse
    {
        $partnerBrandKit->update($this->payload($request));

        return back()->with('success', __('creative::messages.partner_brand_kit.updated'));
    }

    public function destroy(PartnerBrandKit $partnerBrandKit): RedirectResponse
    {
        $partnerBrandKit->delete();

        return back()->with('success', __('creative::messages.partner_brand_kit.deleted'));
    }

    /**
     * Partner logosu yükler; BrandKitController::uploadLogo ile aynı sözleşim.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $path = $request->file('logo')->store('partner_brand_kits/logos', $this->disk());

        return response()->json([
            'path' => $path,
            'url'  => Storage::disk($this->disk())->url($path),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(StorePartnerBrandKitRequest $request): array
    {
        return [
            'name'          => $request->validated('name'),
            'is_active'     => (bool) $request->validated('is_active', true),
            'logo_path'     => $request->validated('logo_path') ?: null,
            'contact'       => $request->validated('contact') ?: null,
            'cta_phrases'   => $request->validated('cta_phrases') ?: null,
            'price_display' => $request->validated('price_display') ?: null,
            'notes'         => $request->validated('notes') ?: null,
        ];
    }

    private function disk(): string
    {
        return config('creative.disk', 'public');
    }
}
