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
            'name' => 'required|string|unique:permissions,name',
            'display_name' => 'nullable|string'
        ]);
        
        Permission::create([
            'name' => $request->name, 
            'display_name' => $request->display_name ?? $request->name,
            'guard_name' => 'web'
        ]);
        
        return back()->with('success', 'Yetki başarıyla oluşturuldu.');
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
            'display_name' => 'nullable|string'
        ]);

        $permission->update([
            'name' => $request->name,
            'display_name' => $request->display_name ?? $request->name
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
