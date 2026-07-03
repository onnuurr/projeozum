<?php
namespace Modules\Superadmin\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function permissions(Role $role)
    {
        Log::info('Role permissions request received', ['role_id' => $role->id, 'role_name' => $role->name]);
        
        try {
            $data = [
                'role' => $role,
                'current_permissions' => $role->permissions->pluck('name'),
                'all_permissions' => Permission::all()->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'display_name' => $p->display_name ?? $p->name
                    ];
                })
            ];
            
            Log::info('Role permissions data prepared successfully');
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in RoleController@permissions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function syncPermissions(Request $request, Role $role)
    {
        Log::info('Sync permissions request received', ['role_id' => $role->id, 'permissions' => $request->permissions]);
        
        try {
            $request->validate([
                'permissions' => 'required|array'
            ]);

            $role->syncPermissions($request->permissions);

            Log::info('Permissions synced successfully');
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error in RoleController@syncPermissions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
{
    $request->validate([
        'role' => 'required|string|unique:roles,name',
        'display_name' => 'nullable|string'
    ]);
    
    // Rolü oluştur
    Role::create([
        'name' => $request->role, 
        'display_name' => $request->display_name ?? $request->role,
        'guard_name' => 'web'
    ]);
    
    return back()->with('success', 'Rol başarıyla oluşturuldu.'); 
}

public function update(Request $request, Role $role)
{
    // superadmin ve tenant: 5 modülün permission seeder'ı bu isimlerle Role::where(...) araması
    // yapıyor. Rename edilirse o seeder'lar rolü bir daha bulamaz ve permission ataması sessizce durur.
    if (in_array($role->name, ['superadmin', 'tenant'], true) && $request->name !== $role->name) {
        return back()->with('error', 'Bu rolün sistem adı değiştirilemez.');
    }

    $request->validate([
        'name' => 'required|string|unique:roles,name,' . $role->id,
        'display_name' => 'nullable|string'
    ]);

    $role->update([
        'name' => $request->name,
        'display_name' => $request->display_name ?? $request->name
    ]);

    return back()->with('success', 'Rol başarıyla güncellendi.');
}
public function destroy(Role $role)
{
    // superadmin ve tenant: sistem rolleri, silinemez (bkz. update() içindeki gerekçe).
    if (in_array($role->name, ['superadmin', 'tenant'], true)) {
        return back()->with('error', 'Bu sistem rolü silinemez.');
    }

    // Bu role atanmış kullanıcılar varsa silmeyi engelle.
    // Bu, hem daha güvenli bir yaklaşımdır hem de kullanıcıya net bilgi verir.
    if ($role->users()->count() > 0) {
        return back()->with('error', 'Bu role atanmış kullanıcılar var. Lütfen önce kullanıcıların rollerini değiştirin.');
    }

    // Silme işleminin sonucunu kontrol et.
    if ($role->delete()) {
        return back()->with('success', 'Rol başarıyla silindi.');
    }

    // Silme işlemi bir sebepten (örn: bir model event'i) başarısız olursa.
    return back()->with('error', 'Bilinmeyen bir hata nedeniyle rol silinemedi.');
}
}