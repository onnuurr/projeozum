<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.bom.manage') ?? false;
    }

    public function rules(): array
    {
        return self::stepRules('steps');
    }

    public function messages(): array
    {
        return self::stepMessages('steps');
    }

    /**
     * Rota adımı kuralları — iş emri sihirbazı, ürün rotası ve Üretilecek Ürün formu aynı şekli kullanır.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function stepRules(string $key, bool $required = true): array
    {
        return [
            $key                          => [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : 'min:0'],
            "{$key}.*.operation_id"       => ['required', 'integer', Rule::exists('operations', 'id')],
            "{$key}.*.sequence"           => ['required', 'integer', 'min:1'],
            "{$key}.*.location_type"      => ['required', Rule::in(['in_house', 'fason'])],
            "{$key}.*.fason_supplier_id"  => ['nullable', "required_if:{$key}.*.location_type,fason", 'integer', Rule::exists('fason_suppliers', 'id')],
            "{$key}.*.unit_cost"          => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public static function stepMessages(string $key): array
    {
        return ["{$key}.*.fason_supplier_id.required_if" => 'Fason aşama için fasoncu seçilmeli.'];
    }
}
