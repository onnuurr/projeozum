<?php
// Modules/Tenant/Http/Requests/UpdatePortalUserRequest.php
namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Services\TenantUserService;

class UpdatePortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::in(TenantUserService::assignableNames())],
        ];
    }
}
