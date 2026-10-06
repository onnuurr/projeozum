<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\StylistOption;

/**
 * Creative ayarlar › Mokap › Stilist seçenekleri: ekle / düzenle. prompt_en görsel prompt'una aynen
 * girer (kombin cümlesini kural motoru bundan kurar) — kısa, somut İngilizce tanım.
 */
class SaveStylistOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mockup-settings.manage') ?? false;
    }

    public function rules(): array
    {
        $option = $this->route('option');

        return [
            'kind'            => ['required', Rule::in(StylistOption::KINDS)],
            'key'             => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('creative_stylist_options', 'key')->where('kind', $this->input('kind'))->ignore($option?->id)],
            'label_tr'        => ['required', 'string', 'max:120'],
            'prompt_en'       => ['nullable', 'string', 'max:255'],
            'is_active'       => ['boolean'],
            'sort_order'      => ['integer', 'min:0', 'max:9999'],
            'tags'            => ['array'],
            'tags.regions'    => ['array'], 'tags.regions.*' => [Rule::in(MockupOutfit::REGIONS)],
            'tags.genders'    => ['array'], 'tags.genders.*' => [Rule::in(['girl', 'boy'])],
            'tags.lengths'    => ['array'], 'tags.lengths.*' => [Rule::in(MockupOutfit::LENGTHS)],
            'tags.style'      => ['array'], 'tags.style.*' => [Rule::in(['casual', 'sporty', 'dressy', 'romantic'])],
            'tags.season'     => ['array'], 'tags.season.*' => [Rule::in(['warm', 'cool'])],
            'tags.colorable'  => ['boolean'],
            'tags.placement'  => ['nullable', Rule::in(['hair', 'wrist', 'chest'])],
        ];
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        $d = $this->validated();

        return [...$d, 'prompt_en' => (string) ($d['prompt_en'] ?? ''), 'tags' => (array) ($d['tags'] ?? [])];
    }
}
