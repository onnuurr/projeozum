<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AtelierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::Dashboard', [
            'stats' => [],
        ]);
    }
}
