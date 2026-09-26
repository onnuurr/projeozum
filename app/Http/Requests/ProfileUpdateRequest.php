<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Telefonu tek biçime getirir: "0 (5xx) xxx xx xx". Maskesiz/farklı yazımlar (5321234567,
     * +90 532 …) da kabul edilir; normalize edilemeyen değer olduğu gibi kalır ve regex'e takılır.
     */
    protected function prepareForValidation(): void
    {
        // Alan hiç gönderilmediyse dokunma; aksi hâlde kayıtlı numara null'a çekilirdi.
        if (! $this->exists('phone')) {
            return;
        }

        $phone = trim((string) $this->input('phone'));

        if ($phone === '') {
            $this->merge(['phone' => null]);

            return;
        }

        $digits = (string) preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $phone = sprintf('0 (%s) %s %s %s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 2), substr($digits, 8, 2));
        }

        $this->merge(['phone' => $phone]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^0 \\([1-9]\\d{2}\\) \\d{3} \\d{2} \\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Telefon numarası 0 (5xx) xxx xx xx biçiminde olmalıdır.',
        ];
    }
}
