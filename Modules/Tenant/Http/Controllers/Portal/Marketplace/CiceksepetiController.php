<?php

namespace Modules\Tenant\Http\Controllers\Portal\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;

class CiceksepetiController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Tenant::Portal/Marketplace/ComingSoon', [
            'tenant'   => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'provider' => ['code' => 'ciceksepeti', 'label' => 'Çiçeksepeti'],
        ]);
    }
}
