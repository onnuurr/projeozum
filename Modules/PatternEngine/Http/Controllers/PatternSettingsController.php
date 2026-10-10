<?php

namespace Modules\PatternEngine\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ModuleSettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PatternEngine\Http\Requests\SaveMeasurementPointRequest;
use Modules\PatternEngine\Http\Requests\SavePatternTypeRequest;
use Modules\PatternEngine\Models\PatternMeasurementPoint;
use Modules\PatternEngine\Models\PatternType;
use Modules\PatternEngine\Services\PatternTypeService;

/**
 * Kalıp Ayarları (çark; atelier.pattern.settings): Kalıp Tipleri (her tipin ölçü şablonu)
 * ve Ölçü Noktaları (vücut / hazır giysi sözlüğü). Yeni sekme eklenince
 * PatternEngineServiceProvider'daki tabPermissions'a yetkisi eklenir.
 */
class PatternSettingsController extends Controller
{
    public function __construct(private PatternTypeService $types) {}

    public function index(Request $request, ModuleSettingsRegistry $registry): Response
    {
        abort_unless($registry->canAccess($request->user(), 'pattern'), 403);

        return Inertia::render('PatternEngine::PatternSettings', [
            'tab'   => $request->query('tab'),
            'types' => PatternType::query()->with('measurementPoints:id')->withCount('patterns')
                ->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (PatternType $t) => [
                    'id'            => $t->id,
                    'name'          => $t->name,
                    'is_active'     => $t->is_active,
                    'sort_order'    => $t->sort_order,
                    'pattern_count' => $t->patterns_count,
                    'point_ids'     => $t->measurementPoints->pluck('id')->values(),
                ]),
            'points' => PatternMeasurementPoint::query()->orderBy('basis')->orderBy('sort_order')->get()
                ->map(fn (PatternMeasurementPoint $p) => $p->only(['id', 'key', 'name', 'hint', 'basis', 'unit', 'sort_order', 'is_active'])),
        ]);
    }

    public function storeType(SavePatternTypeRequest $request): RedirectResponse
    {
        $this->types->saveType(null, $request->safe()->except('point_ids'), $request->validated('point_ids') ?? []);

        return back()->with('success', __('patternengine::messages.pattern_settings.type_saved'));
    }

    public function updateType(SavePatternTypeRequest $request, PatternType $type): RedirectResponse
    {
        $this->types->saveType($type, $request->safe()->except('point_ids'), $request->validated('point_ids') ?? []);

        return back()->with('success', __('patternengine::messages.pattern_settings.type_saved'));
    }

    public function destroyType(PatternType $type): RedirectResponse
    {
        $deleted = $this->types->deleteType($type);

        return back()->with('success', __('patternengine::messages.pattern_settings.' . ($deleted ? 'type_deleted' : 'type_deactivated')));
    }

    public function storePoint(SaveMeasurementPointRequest $request): RedirectResponse
    {
        $this->types->savePoint(null, $request->validated());

        return back()->with('success', __('patternengine::messages.pattern_settings.point_saved'));
    }

    public function updatePoint(SaveMeasurementPointRequest $request, PatternMeasurementPoint $point): RedirectResponse
    {
        $this->types->savePoint($point, $request->validated());

        return back()->with('success', __('patternengine::messages.pattern_settings.point_saved'));
    }

    public function destroyPoint(PatternMeasurementPoint $point): RedirectResponse
    {
        $deleted = $this->types->deletePoint($point);

        return back()->with('success', __('patternengine::messages.pattern_settings.' . ($deleted ? 'point_deleted' : 'point_deactivated')));
    }
}
