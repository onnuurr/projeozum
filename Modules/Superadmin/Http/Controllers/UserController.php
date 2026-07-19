<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with(['roles:id,name', 'tenant:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'job_title'  => $u->job_title,
                'is_active'  => (bool) $u->is_active,
                'role'       => $u->roles->first()?->name,
                'tenant'     => $u->tenant ? ['id' => $u->tenant->id, 'name' => $u->tenant->name] : null,
                'created_at' => $u->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Superadmin::Users', [
            'users'   => $users,
            'roles'   => Role::query()->orderBy('name')->get(['id', 'name', 'display_name']),
            'tenants' => Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'phone'     => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);

        // Flash basılmıyor — Users.vue onSuccess'te kendi toast'unu gösteriyor.
        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        if (! $user->hasRole('superadmin')) {
            $user->syncRoles([$data['role']]);
        }

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        // withErrors() kullanılıyor ki Inertia bunu bir validation hatası gibi ele alıp
        // frontend'in onError callback'ini tetiklesin — düz back()->with('error', ...)
        // normal bir redirect olduğu için onSuccess'i tetikleyip Users.vue'de yanlışlıkla
        // "Kullanıcı silindi" toast'unu da gösteriyordu.
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Kendi hesabınızı silemezsiniz.']);
        }
        if ($user->hasRole('superadmin') && User::role('superadmin')->count() <= 1) {
            return back()->withErrors(['user' => 'Son superadmin hesabı silinemez.']);
        }

        $user->delete();

        // Flash basılmıyor — Users.vue onSuccess'te kendi toast'unu gösteriyor.
        return back();
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Kendi hesabınızı pasifleştiremezsiniz.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back();
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?User $editing): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($editing?->id)],
            'password'  => [$editing ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);
    }
}
