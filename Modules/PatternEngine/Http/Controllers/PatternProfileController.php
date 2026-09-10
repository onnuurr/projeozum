<?php

namespace Modules\PatternEngine\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PatternEngine\DTOs\ProvenanceDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;
use Modules\PatternEngine\Exceptions\PatternGenerationFailedException;
use Modules\PatternEngine\Exceptions\ProfileProtectedException;
use Modules\PatternEngine\Http\Requests\SaveProfileRequest;
use Modules\PatternEngine\Services\PatternEngineService;

/**
 * Çok profillilik yönetimi (§5.2, Sprint 4) — bir firmanın/modelistin kendi
 * ölçü tablosunu (`sizes.yaml`) `ts_default`'tan bağımsız olarak tanımlamasını
 * sağlar. Çizim formülleri (`rules.yaml`) her zaman bir taban profilden
 * kopyalanır; burada yalnızca ölçü tablosu düzenlenir.
 */
class PatternProfileController extends Controller
{
    public function __construct(private readonly PatternEngineService $service) {}

    public function index(): Response
    {
        return Inertia::render('PatternEngine::PatternProfiles', [
            'profiles' => $this->service->listProfiles(),
        ]);
    }

    public function store(SaveProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $provenance = new ProvenanceDTO(
            source: $data['provenance']['source'],
            verifiedByModelist: (bool) ($data['provenance']['verified_by_modelist'] ?? false),
            note: $data['provenance']['note'] ?? null,
        );

        try {
            $this->service->saveProfile(new SaveProfileRequestDTO(
                name: $data['name'],
                sizes: $data['sizes'],
                provenance: $provenance,
                baseProfile: $data['base_profile'] ?? 'ts_default',
            ));
        } catch (ProfileProtectedException $e) {
            return back()->withInput()->withErrors(['name' => $e->getMessage()]);
        } catch (PatternGenerationFailedException $e) {
            return back()->withInput()->withErrors(['sizes' => $e->getMessage()]);
        }

        return redirect()->route('patternengine.profiles.index');
    }
}
