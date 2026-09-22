<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportPatternFromUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.manage') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    /**
     * 'url' kuralı şema+biçimi doğrular; host'un izinli tedarikçi domain'lerinden
     * biri olduğunu burada ayrıca kontrol ederiz (SSRF önleme — bkz. config/atelier.php
     * pattern_import.allowed_hosts).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $url = $v->getData()['url'] ?? null;
            if (! is_string($url) || $url === '') {
                return;
            }

            $allowed = (array) config('atelier.pattern_import.allowed_hosts', []);
            $scheme  = parse_url($url, PHP_URL_SCHEME);
            $host    = parse_url($url, PHP_URL_HOST);

            if ($scheme !== 'https' || $host === null || $host === '' || ! self::hostAllowed($host, $allowed)) {
                $v->errors()->add(
                    'url',
                    'Bu adresten içe aktarım desteklenmiyor. İzinli kaynaklar: ' . implode(', ', $allowed) . '.',
                );
            }
        });
    }

    /** @param  array<int,string>  $allowed */
    public static function hostAllowed(string $host, array $allowed): bool
    {
        $host = strtolower($host);
        foreach ($allowed as $a) {
            $a = strtolower(trim($a));
            if ($a === '') {
                continue;
            }
            if ($host === $a || str_ends_with($host, '.' . $a)) {
                return true;
            }
        }

        return false;
    }
}
