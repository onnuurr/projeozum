<?php

namespace Modules\Superadmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class Menu extends Model
{
    protected $table = 'superadmin_menus';

    protected $fillable = [
        'parent_id', 'label', 'icon', 'route_name', 'url',
        'permission', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'parent_id'  => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * permission null → herkese görünür.
     * Aksi halde kullanıcının izni olmalı (superadmin Gate::before ile geçer).
     */
    public function isVisibleTo(?User $user): bool
    {
        if (! $this->permission) {
            return true;
        }
        return (bool) $user?->can($this->permission);
    }

    /**
     * Frontend 'to' değeri: route varsa göreli path, yoksa url, ikisi de yoksa null.
     * route(..., [], false) → "/products" gibi göreli yol (active tespiti path ile çalışır).
     */
    public function resolveTo(): ?string
    {
        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name, [], false);
        }
        return $this->url ?: null;
    }
}
