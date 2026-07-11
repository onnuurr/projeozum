<?php

namespace Modules\Marketplace\Http\Controllers\Portal\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;

class N11Controller extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Marketplace::Portal/Marketplace/ComingSoon', [
            'tenant'   => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'provider' => ['code' => 'n11', 'label' => 'N11'],
        ]);
    }
}
