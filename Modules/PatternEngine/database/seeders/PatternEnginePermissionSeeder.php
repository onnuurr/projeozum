<?php

namespace Modules\PatternEngine\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PatternEnginePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'patternengine.manage'         => 'Kalıp Motoru Yönet',
            // Üretim/gradasyon yetkisinden AYRI: modelist ölçü tablosu giren kişiye
            // kalıp üretme yetkisi vermeden bu izni tek başına verebilmek için
            // (Atelier'in atelier.view + atelier.material.manage hibrit deseniyle aynı).
            'patternengine.profile.manage' => 'Kalıp Motoru Profil Yönet',
            // Kalıp Ayarları (çark): kalıp tipleri + ölçü noktası sözlüğü. Kalıp düzenleyen
            // (atelier.pattern.manage) herkes ölçü şablonlarını değiştiremesin diye ayrı (2026-10-07).
            'atelier.pattern.settings'     => 'Kalıp Ayarları Yönet',
        ];

        $created = [];
        foreach ($permissions as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $created[] = $perm->name;
        }

        // Yalnızca superadmin/iç kullanım — henüz gerçek ürün akışına bağlı değil (Sprint 1).
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }
    }
}
