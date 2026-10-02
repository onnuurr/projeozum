<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Süper Admin › Dil Dosyaları kaydı. `changes` = { locale: { "nokta.yolu": "değer" } }.
 * Anahtarlar nokta içerdiği için `changes.*.*` kuralı yazılamaz (Laravel onu dizi yolu sanar);
 * anahtar/dil/dosya doğrulaması kaynakta (LangFileStore / Bagisto TranslationFiles) yapılır.
 */
class UpdateTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('translations.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:32'],
            'group' => ['required', 'string', 'max:191'],
            'file' => ['required', 'string', 'max:64'],
            'changes' => ['required', 'array'],
            'fill_missing_locales' => ['boolean'],
        ];
    }

    /**
     * Metin olmayan değerler atılır; null (boş bırakılmış alan) boş metin olarak yazılır.
     *
     * @return array<string, array<string, string>>
     */
    public function changes(): array
    {
        $out = [];

        foreach ((array) $this->input('changes') as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $key => $value) {
                if ($value === null || is_scalar($value)) {
                    $out[(string) $locale][(string) $key] = (string) ($value ?? '');
                }
            }
        }

        return $out;
    }
}
