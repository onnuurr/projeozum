<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StoreBrandKitRequest;
use Modules\Creative\Models\BrandKit;
use Modules\Creative\Services\BrandTokenService;

class BrandKitController extends Controller
{
    public function __construct(private BrandTokenService $brandTokens) {}

    public function index(): Response
    {
        $kits = BrandKit::query()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_default', 'palette', 'typography', 'logos', 'spacing'])
            ->map(fn (BrandKit $k) => [
                'id'         => $k->id,
                'name'       => $k->name,
                'is_default' => $k->is_default,
                'palette'    => (object) ($k->palette ?? []),
                'typography' => (object) ($k->typography ?? []),
                'logos'      => (object) ($k->logos ?? []),
                'spacing'    => (object) ($k->spacing ?? []),
            ]);

        return Inertia::render('Creative::CreativeBrandKits', [
            'kits'     => $kits,
            'defaults' => config('creative.brand.defaults', []),
        ]);
    }

    public function store(StoreBrandKitRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $kit = BrandKit::create($this->payload($request));
            $this->syncDefault($kit);
        });

        $this->brandTokens->forget();

        return back()->with('success', 'Marka kiti oluşturuldu.');
    }

    public function update(StoreBrandKitRequest $request, BrandKit $brandKit): RedirectResponse
    {
        DB::transaction(function () use ($request, $brandKit) {
            $brandKit->update($this->payload($request));
            $this->syncDefault($brandKit);
        });

        $this->brandTokens->forget();

        return back()->with('success', 'Marka kiti güncellendi.');
    }

    public function destroy(BrandKit $brandKit): RedirectResponse
    {
        $brandKit->delete();
        $this->brandTokens->forget();

        return back()->with('success', 'Marka kiti silindi.');
    }

    /**
     * Logo dosyası yükler; kaydedilen göreli yolu + public URL'i JSON döndürür.
     * (Editör bu yolu logos.<variant> alanına yazar.)
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $path = $request->file('logo')->store('brand_kits/logos', $this->disk());

        return response()->json([
            'path' => $path,
            'url'  => Storage::disk($this->disk())->url($path),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(StoreBrandKitRequest $request): array
    {
        return [
            'name'       => $request->validated('name'),
            'is_default' => (bool) $request->validated('is_default', false),
            'palette'    => $request->validated('palette') ?: null,
            'typography' => $request->validated('typography') ?: null,
            'logos'      => $request->validated('logos') ?: null,
            'spacing'    => $request->validated('spacing') ?: null,
        ];
    }

    /**
     * Bu kit varsayılansa diğerlerinin varsayılanını kaldırır (tekil default).
     */
    private function syncDefault(BrandKit $kit): void
    {
        if ($kit->is_default) {
            BrandKit::query()
                ->whereKeyNot($kit->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    private function disk(): string
    {
        return config('creative.disk', 'public');
    }
}
