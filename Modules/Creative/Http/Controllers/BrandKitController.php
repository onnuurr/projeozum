<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\StoreBrandKitRequest;
use Modules\Creative\Models\BrandKit;
use Modules\Creative\Services\BrandTokenService;

class BrandKitController extends Controller
{
    /** Render motoru ttf/otf'i güvenle kullanır; woff/woff2 saklanır ama destek motora bağlıdır. */
    private const FONT_EXTS = ['ttf', 'otf', 'woff', 'woff2'];

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
     * Font yükler: tek dosya (ttf/otf/woff/woff2) ya da .zip (içinden font dosyalarını
     * çıkarır). Saklanan göreli yol(lar)ı + adlarını JSON döndürür; editör bunları
     * tipografi font kütüphanesine ekler.
     */
    public function uploadFont(Request $request): JsonResponse
    {
        $request->validate([
            'font' => ['required', 'file', 'max:20480'], // 20MB (zip dahil)
        ]);

        $file = $request->file('font');
        $ext  = strtolower($file->getClientOriginalExtension());

        if ($ext === 'zip') {
            $fonts = $this->extractFontsFromZip($file->getRealPath());
            if (empty($fonts)) {
                return response()->json(['message' => 'ZIP içinde font (ttf/otf/woff/woff2) bulunamadı.'], 422);
            }

            return response()->json(['fonts' => $fonts]);
        }

        if (! in_array($ext, self::FONT_EXTS, true)) {
            return response()->json([
                'message' => 'Desteklenmeyen dosya türü. ttf/otf/woff/woff2 veya .zip yükleyin.',
            ], 422);
        }

        $path = $file->store('brand_kits/fonts', $this->disk());

        return response()->json(['fonts' => [
            ['name' => $file->getClientOriginalName(), 'path' => $path],
        ]]);
    }

    /**
     * ZIP içindeki font dosyalarını storage'a açar.
     *
     * @return array<int,array{name:string,path:string}>
     */
    private function extractFontsFromZip(string $zipPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        $fonts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            $base  = basename($entry); // path traversal'a karşı yalnız dosya adı
            $fext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));

            if (! in_array($fext, self::FONT_EXTS, true)) {
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false || $contents === '') {
                continue;
            }

            $stored = sprintf(
                'brand_kits/fonts/%s-%s.%s',
                Str::random(8),
                Str::slug(pathinfo($base, PATHINFO_FILENAME)) ?: 'font',
                $fext,
            );
            Storage::disk($this->disk())->put($stored, $contents);

            $fonts[] = ['name' => $base, 'path' => $stored];
        }
        $zip->close();

        return $fonts;
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
