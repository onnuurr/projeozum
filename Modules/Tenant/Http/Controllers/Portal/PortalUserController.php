<?php
// Modules/Tenant/Http/Controllers/Portal/PortalUserController.php
namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Http\Requests\StorePortalUserRequest;
use Modules\Tenant\Http\Requests\UpdatePortalUserRequest;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantUserService;

class PortalUserController extends Controller
{
    public function __construct(private TenantUserService $service) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'is_active'   => (bool) $u->is_active,
                'is_admin'    => $u->hasRole('tenant'),
                'permissions' => $u->getDirectPermissions()->pluck('name')->values(),
            ]);

        return Inertia::render('Tenant::Portal/Users/Index', [
            'tenant'                => [
                'id' => $tenant->id, 'code' => $tenant->code,
                'name' => $tenant->name, 'slug' => $tenant->slug,
            ],
            'users'                 => $users,
            'assignablePermissions' => collect(TenantUserService::ASSIGNABLE_PERMISSIONS)
                ->map(fn ($meta, $name) => ['name' => $name, 'label' => $meta['label'], 'group' => $meta['group']])
                ->values(),
        ]);
    }

    public function store(StorePortalUserRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $this->service->create($tenant, $request->validated());

        // route('portal.users.index') kullanılamaz: tenant.subdomain middleware {slug}'ı
        // forgetParameter ile düşürdüğü için named-route URL üretimi UrlGenerationException verir.
        // Mevcut host'tan mutlak URL kur.
        return redirect($request->getSchemeAndHttpHost() . '/users')->with('success', 'Kullanıcı eklendi.');
    }

    public function update(UpdatePortalUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);

        $this->service->update($user, $request->validated());

        // route('portal.users.index') kullanılamaz: tenant.subdomain middleware {slug}'ı
        // forgetParameter ile düşürdüğü için named-route URL üretimi UrlGenerationException verir.
        // Mevcut host'tan mutlak URL kur.
        return redirect($request->getSchemeAndHttpHost() . '/users')->with('success', 'Kullanıcı güncellendi.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);
        if ($user->id === $request->user()->id) {
            abort(403, 'Kendinizi silemezsiniz.');
        }

        $this->service->delete($user);

        // route('portal.users.index') kullanılamaz: tenant.subdomain middleware {slug}'ı
        // forgetParameter ile düşürdüğü için named-route URL üretimi UrlGenerationException verir.
        // Mevcut host'tan mutlak URL kur.
        return redirect($request->getSchemeAndHttpHost() . '/users')->with('success', 'Kullanıcı silindi.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);
        if ($user->id === $request->user()->id) {
            abort(403, 'Kendinizi pasifleştiremezsiniz.');
        }

        $this->service->toggleActive($user);

        return back()->with('success', $user->fresh()->is_active ? 'Kullanıcı aktifleştirildi.' : 'Kullanıcı pasifleştirildi.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->service->resetPassword($user, $data['password']);

        return back()->with('success', 'Şifre güncellendi.');
    }
}
