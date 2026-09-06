<?php

namespace Modules\Tenant\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Tenant\Support\TurkishTaxNumberValidator;

class ValidTaxNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if ($value === '') {
            // Boşluk kontrolü required/nullable kuralının işi — bu kural sadece
            // dolu bir değerin checksum'ını doğrular.
            return;
        }

        if (! TurkishTaxNumberValidator::isValid($value)) {
            $fail('Geçersiz vergi kimlik numarası / TC kimlik numarası.');
        }
    }
}
