<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'is_default'    => ['sometimes', 'boolean'],

            // Renk token'ları: {"primary":"#rrggbb", ...}
            'palette'       => ['nullable', 'array'],
            'palette.*'     => ['nullable', 'string', 'max:32'],

            // Tipografi: {"regular":"path","bold":"path","fonts":[{"name","path"}]}
            'typography'              => ['nullable', 'array'],
            'typography.regular'      => ['nullable', 'string', 'max:1024'],
            'typography.bold'         => ['nullable', 'string', 'max:1024'],
            'typography.fonts'        => ['nullable', 'array'],
            'typography.fonts.*.name' => ['nullable', 'string', 'max:191'],
            'typography.fonts.*.path' => ['required_with:typography.fonts.*', 'string', 'max:1024'],

            // Boşluk token'ları: {"sm":8,"md":16,...}
            'spacing'       => ['nullable', 'array'],
            'spacing.*'     => ['nullable', 'integer', 'min:0', 'max:9999'],

            // Logo varyant yolları: {"primary":"path","mono":"path"}
            'logos'         => ['nullable', 'array'],
            'logos.*'       => ['nullable', 'string', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Boş string değerleri null'a indir; palette/spacing/logos boş anahtarları at.
        foreach (['palette', 'typography', 'logos'] as $field) {
            if (is_array($this->input($field))) {
                $this->merge([
                    $field => array_filter(
                        $this->input($field),
                        fn ($v) => is_string($v) ? trim($v) !== '' : $v !== null,
                    ),
                ]);
            }
        }
    }
}
