<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Http\Requests\UpdateMaterialSpecsRequest;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Services\MaterialSpecService;
use Modules\Atelier\Services\MaterialStockService;

class MaterialController extends Controller
{
    public function __construct(
        private MaterialStockService $stock,
        private MaterialSpecService $specs,
    ) {}

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
                'specs'        => (array) ($m->specs ?? []),
            ]);

        return Inertia::render('Atelier::Materials', [
            'materials' => $materials,
        ]);
    }

    public function updateSpecs(UpdateMaterialSpecsRequest $request, Material $material): RedirectResponse
    {
        $this->specs->updateSpecs($material, $request->specs());

        return redirect()->route('atelier.materials.index')
            ->with('success', 'Malzeme özellikleri güncellendi.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMaterial($request);
        $initial = (float) ($request->validate([
            'initial_quantity' => ['nullable', 'numeric', 'min:0'],
        ])['initial_quantity'] ?? 0);

        // Alınan miktar girildiyse açılış stoğu satın alma hareketi olarak yazılır
        // (current_stock doğrudan set edilmez; hareket geçmişi eksiksiz kalsın).
        DB::transaction(function () use ($data, $initial) {
            $material = Material::create($data);

            if ($initial > 0) {
                $this->stock->record($material, MaterialMovement::TYPE_IN, $initial, 'purchase', [
                    'unit_cost' => $data['unit_cost'],
                    'note'      => 'İlk alım (hammadde kaydı)',
                ]);
            }
        });

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
