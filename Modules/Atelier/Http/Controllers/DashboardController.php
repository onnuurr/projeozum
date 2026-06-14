<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $active = ProductionOrder::query()
            ->whereIn('status', [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS])
            ->with('product:id,name')
            ->orderBy('due_date')
            ->get()
            ->map(fn (ProductionOrder $o) => [
                'id' => $o->id, 'code' => $o->code, 'productName' => $o->product?->name,
                'status' => $o->status, 'dueDate' => optional($o->due_date)->format('Y-m-d'),
                'isLate' => $o->due_date && $o->due_date->isPast(),
            ]);

        $fasonPending = ProductionOrderStep::query()
            ->where('location_type', 'fason')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->count();

        $lowStock = Material::query()
            ->where('is_active', true)
            ->where('current_stock', '<=', 0)
            ->count();

        return Inertia::render('Atelier::Dashboard', [
            'activeOrders' => $active,
            'fasonPending' => $fasonPending,
            'lowStock'     => $lowStock,
        ]);
    }
}
