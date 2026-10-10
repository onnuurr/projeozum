<?php

namespace Modules\Bagisto\Console;

use Illuminate\Console\Command;
use Modules\Bagisto\Jobs\PushAttributeOptionToBagisto;
use Modules\Bagisto\Jobs\PushProductAttributeToBagisto;
use Modules\Product\Models\ProductAttribute;

/**
 * Renk/beden/marka seçenekleri ile Ürün Ayarları → Özellikler tanımlarını ve seçeneklerini
 * Bagisto'ya gönderir (backfill). Önce özellik tanımları dizilir ki seçenekleri bulacak attribute
 * olsun. Bagisto'da aynı adla duran seçenek güncellenir (swatch/sıra), yoksa açılır.
 * Bagisto'da olup SaaS'ta olmayan seçeneklere dokunulmaz.
 */
class SyncAttributeOptionsCommand extends Command
{
    protected $signature = 'bagisto:sync-attribute-options';

    protected $description = 'Renk/beden, marka ve Ürün Ayarları özelliklerini (tanım + seçenek) Bagisto\'ya push etmek için kuyruğa diziler (backfill).';

    public function handle(): int
    {
        $total = 0;

        foreach (PushAttributeOptionToBagisto::MODELS as $model) {
            foreach ($model::orderBy('sort_order')->get() as $option) {
                dispatch(PushAttributeOptionToBagisto::forOption($option));
                $total++;
            }
        }

        $attributes = ProductAttribute::with('options')->whereNotIn('code', ProductAttribute::SYSTEM_CODES)->orderBy('sort_order')->get();
        foreach ($attributes as $attribute) {
            dispatch(PushProductAttributeToBagisto::forAttribute($attribute));
            foreach ($attribute->options as $option) {
                dispatch(PushAttributeOptionToBagisto::forOption($option));
                $total++;
            }
        }

        $this->info("{$attributes->count()} özellik ve {$total} seçenek Bagisto senkron kuyruğuna dizildi.");

        return self::SUCCESS;
    }
}
