<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Services\MaterialStockService;

class MaterialController extends Controller
{
    public function __construct(private MaterialStockService $stock) {}

    public function index(): Response
    {
        $materials = Material::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => [
                'id'           => $m->id,
                'code'         => $m->code,
                'name'         => $m->name,
                'type'         => $m->type,
                'unit'         => $m->unit,
                'unitCost'     => (float) $m->unit_cost,
                'currentStock' => (float) $m->current_stock,
                'isActive'     => $m->is_active,
            ]);

        return Inertia::render('Atelier::Materials', [
            'materials' => $materials,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Material::create($this->validateMaterial($request));

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde eklendi.');
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $material->update($this->validateMaterial($request, $material->id));

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde güncellendi.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde silindi.');
    }

    public function movement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'type'        => ['required', Rule::in(['in', 'out', 'adjust'])],
            'quantity'    => ['required', 'numeric', 'not_in:0'],
            'reason'      => ['required', Rule::in(['purchase', 'consume', 'scrap', 'correction'])],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        $material = Material::findOrFail($data['material_id']);

        try {
            $this->stock->record(
                $material,
                $data['type'],
                (float) $data['quantity'],
                $data['reason'],
                ['unit_cost' => $data['unit_cost'] ?? null, 'note' => $data['note'] ?? null],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('atelier.materials.index')->with('success', 'Stok hareketi kaydedildi.');
    }

    private function validateMaterial(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'      => ['required', 'string', 'max:32', Rule::unique('materials', 'code')->ignore($ignoreId)],
            'name'      => ['required', 'string', 'max:191'],
            'type'      => ['required', Rule::in(['kumas', 'aksesuar', 'etiket'])],
            'unit'      => ['required', Rule::in(['metre', 'adet', 'kg'])],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
