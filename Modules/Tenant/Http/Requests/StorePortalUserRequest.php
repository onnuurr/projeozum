<?php
// Modules/Tenant/Http/Requests/StorePortalUserRequest.php
namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Services\TenantUserService;

class StorePortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'email'         => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::in(TenantUserService::assignableNames())],
        ];
    }
}
