<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Operation;

class OperationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::Operations', [
            'operations' => Operation::query()->orderBy('sort_order')->get()->map(fn (Operation $o) => [
                'id'              => $o->id,
                'code'            => $o->code,
                'name'            => $o->name,
                'defaultLocation' => $o->default_location,
                'defaultUnitCost' => (float) $o->default_unit_cost,
                'sortOrder'       => $o->sort_order,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Operation::create($this->validateOperation($request));

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon eklendi.');
    }

    public function update(Request $request, Operation $operation): RedirectResponse
    {
        $operation->update($this->validateOperation($request, $operation->id));

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon güncellendi.');
    }

    public function destroy(Operation $operation): RedirectResponse
    {
        $operation->delete();

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon silindi.');
    }

    private function validateOperation(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'              => ['required', 'string', 'max:32', Rule::unique('operations', 'code')->ignore($ignoreId)],
            'name'              => ['required', 'string', 'max:191'],
            'default_location'  => ['required', Rule::in(['in_house', 'fason'])],
            'default_unit_cost' => ['required', 'numeric', 'min:0'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
