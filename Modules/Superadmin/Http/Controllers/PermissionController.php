<?php
namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required', 'string', 'unique:permissions,name',
                'regex:/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/',
            ],
            'display_name' => 'nullable|string'
        ]);

        Permission::create([
            'name' => $request->name,
            'display_name' => $request->display_name ?? $request->name,
            'guard_name' => 'web'
        ]);

        return back()->with('success', 'Yetki başarıyla oluşturuldu.');
    }

    /**
     * `name` route middleware'lerinde (`can:izin.adi`) hardcoded olarak kullanıldığı için
     * UI'dan değiştirilemez — rename, kodda ilgili seeder + route'ların birlikte
     * güncellenmesini gerektiren bir geliştirici işlemidir. Sadece display_name düzenlenir.
     */
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'display_name' => 'nullable|string'
        ]);

        $permission->update([
            'display_name' => $request->display_name ?? $permission->name,
        ]);

        return back()->with('success', 'Yetki başarıyla güncellendi.');
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->count() > 0) {
            return back()->with('error', 'Bu yetki bazı rollere atanmış durumda. Önce ilişkileri kesin.');
        }

        $permission->delete();

        return back()->with('success', 'Yetki başarıyla silindi.');
    }
}
