<?php

namespace Modules\Superadmin\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Superadmin\Models\Menu;

class MenuTreeBuilder
{
    /**
     * Aktif + izinli menülerden, kullanıcıya görünür ağacı kurar.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        $all = Menu::query()->active()->ordered()->get();

        return self::build($all, null, $user);
    }

    /**
     * @param  Collection<int, Menu>  $all
     * @return array<int, array<string, mixed>>
     */
    private static function build(Collection $all, ?int $parentId, ?User $user): array
    {
        return $all
            ->where('parent_id', $parentId)
            ->filter(fn (Menu $m) => $m->isVisibleTo($user))
            ->map(fn (Menu $m) => [
                'id'         => $m->id,
                'label'      => $m->label,
                'icon'       => $m->icon,
                'to'         => $m->resolveTo(),
                'permission' => $m->permission,
                'children'   => self::build($all, $m->id, $user),
            ])
            ->values()
            ->all();
    }
}
