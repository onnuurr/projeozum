<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Atelier\Models\Material;

/**
 * MaterialPicker autocomplete için lookup. Yalnız aktif materyalleri döndürür.
 */
class MaterialLookupController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q    = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');

        $query = Material::query()
            ->where('is_active', true)
            ->limit(20)
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%");
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        return response()->json([
            'data' => $query->get(['id', 'code', 'name', 'type', 'unit', 'specs'])
                ->map(fn (Material $m) => [
                    'id'    => $m->id,
                    'code'  => $m->code,
                    'name'  => $m->name,
                    'type'  => $m->type,
                    'unit'  => $m->unit,
                    'specs' => (array) ($m->specs ?? []),
                ])->values(),
        ]);
    }
}
