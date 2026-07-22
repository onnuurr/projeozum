<?php

namespace Modules\Product\Enums;

/**
 * Ürünün belirli bir akış için "hazır" sayılması gereken yetenek kümesi
 * (Faz 4 — Readiness Engine). `ProductReadinessService::evaluate()` bu
 * enum'a göre kontrol setini seçer.
 */
enum Capability: string
{
    case TryOn = 'try_on';
    case CreativeRender = 'creative_render';
    case MarketplacePush = 'marketplace_push';

    public function label(): string
    {
        return match ($this) {
            self::TryOn => 'Sanal Giydirme',
            self::CreativeRender => 'Kreatif Görsel Üretimi',
            self::MarketplacePush => 'Pazaryeri Gönderimi',
        };
    }
}
