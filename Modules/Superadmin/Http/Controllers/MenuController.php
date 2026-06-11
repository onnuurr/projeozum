<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Http\Requests\ReorderMenuRequest;
use Modules\Superadmin\Http\Requests\StoreMenuRequest;
use Modules\Superadmin\Http\Requests\UpdateMenuRequest;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Superadmin::Menus', [
            'menus'       => Menu::query()->ordered()->get([
                'id', 'parent_id', 'label', 'icon', 'route_name',
                'url', 'permission', 'sort_order', 'is_active',
            ]),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        Menu::create($request->validated());

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü eklendi'],
        ]);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü güncellendi'],
        ]);
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        // FK cascadeOnDelete alt ağacı da siler.
        $menu->delete();

        return back()->with('flash', [
            'toast' => ['type' => 'info', 'title' => 'Menü silindi'],
        ]);
    }

    public function reorder(ReorderMenuRequest $request): RedirectResponse
    {
        $items = $request->validated()['items'];

        // Döngü kontrolü: önerilen parent zincirinde öğenin kendisi geçiyorsa reddet.
        $proposed = collect($items)->mapWithKeys(
            fn ($i) => [$i['id'] => $i['parent_id'] ?? null]
        );
        // Mevcut parent'ları da hesaba kat (payload kısmi olabilir).
        $existing = Menu::query()->pluck('parent_id', 'id');
        $resolve  = fn ($id) => $proposed->has($id) ? $proposed[$id] : ($existing[$id] ?? null);

        foreach ($items as $item) {
            $cursor = $resolve($item['id']);
            $guard  = 0;
            while ($cursor !== null) {
                if ($cursor === $item['id'] || ++$guard > 1000) {
                    return back()->withErrors([
                        'items' => 'Bir menü kendi alt menüsünün altına taşınamaz (döngü).',
                    ]);
                }
                $cursor = $resolve($cursor);
            }
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Menu::query()->whereKey($item['id'])->update([
                    'parent_id'  => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü sıralaması kaydedildi'],
        ]);
    }
}
