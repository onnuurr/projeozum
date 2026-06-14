<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\FasonSupplier;

class FasonSupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::FasonSuppliers', [
            'suppliers' => FasonSupplier::query()->orderBy('name')->get()->map(fn (FasonSupplier $s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'contactName' => $s->contact_name,
                'phone'       => $s->phone,
                'email'       => $s->email,
                'address'     => $s->address,
                'taxNo'       => $s->tax_no,
                'notes'       => $s->notes,
                'isActive'    => $s->is_active,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FasonSupplier::create($this->validateSupplier($request));

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu eklendi.');
    }

    public function update(Request $request, FasonSupplier $fasonSupplier): RedirectResponse
    {
        $fasonSupplier->update($this->validateSupplier($request));

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu güncellendi.');
    }

    public function destroy(FasonSupplier $fasonSupplier): RedirectResponse
    {
        $fasonSupplier->delete();

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu silindi.');
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'phone'        => ['nullable', 'string', 'max:32'],
            'email'        => ['nullable', 'email', 'max:191'],
            'address'      => ['nullable', 'string', 'max:2000'],
            'tax_no'       => ['nullable', 'string', 'max:32'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['boolean'],
        ]);
    }
}
