<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Modules\Tenant\Rules\ValidTaxNumber;

class StoreTenantApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Herkese açık başvuru formu — kimlik doğrulama gerekmez.
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:191'],
            'legal_name'      => ['nullable', 'string', 'max:255'],
            'tax_number'      => ['required', 'string', 'max:32', new ValidTaxNumber()],
            'tax_office'      => ['nullable', 'string', 'max:191'],
            'contact_person'  => ['required', 'string', 'max:191'],
            'contact_email'   => ['required', 'email', 'max:191'],
            'contact_phone'   => ['nullable', 'string', 'max:32'],
            'address'         => ['nullable', 'string', 'max:1000'],
            'city'            => ['nullable', 'string', 'max:100'],
            'district'        => ['nullable', 'string', 'max:100'],
            'country'         => ['nullable', 'string', 'max:100'],
            'postal_code'     => ['nullable', 'string', 'max:16'],
            'message'         => ['nullable', 'string', 'max:2000'],

            // Bot koruması — gizli alan, insan kullanıcıda hep boş kalır.
            'website'         => ['prohibited'],

            'vergi_levhasi'          => ['required', File::default()->max(10 * 1024)->extensions(['pdf', 'jpg', 'jpeg', 'png'])],
            'ticaret_sicil_gazetesi' => ['nullable', File::default()->max(10 * 1024)->extensions(['pdf', 'jpg', 'jpeg', 'png'])],
            'imza_sirkuleri'         => ['nullable', File::default()->max(10 * 1024)->extensions(['pdf', 'jpg', 'jpeg', 'png'])],
        ];
    }
}
