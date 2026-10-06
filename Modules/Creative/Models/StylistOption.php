<?php

namespace Modules\Creative\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Mokap stilistinin seçebileceği kombin parçası / renk (bkz. MockupStylistRuleEngine).
 * tags: regions, genders, lengths, style, season, colorable, placement — açıklama migration'da.
 */
class StylistOption extends Model
{
    protected $table = 'creative_stylist_options';

    protected $fillable = ['kind', 'key', 'label_tr', 'prompt_en', 'tags', 'is_active', 'sort_order'];

    protected $casts = [
        'tags'       => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public const KINDS = ['top', 'bottom', 'footwear', 'socks', 'accessory', 'color'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Kural motorunun beklediği düz yapı: kind => key => {label_tr, prompt_en, tags}, sort_order sırasıyla.
     *
     * @return array<string,array<string,array{label_tr:string,prompt_en:string,tags:array<string,mixed>}>>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (static::active()->orderBy('kind')->orderBy('sort_order')->get() as $o) {
            $out[$o->kind][$o->key] = ['label_tr' => $o->label_tr, 'prompt_en' => $o->prompt_en, 'tags' => $o->tags ?? []];
        }

        return $out;
    }
}
