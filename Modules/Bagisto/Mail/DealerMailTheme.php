<?php

namespace Modules\Bagisto\Mail;

/**
 * Bayi e-postalarının (başvuru, iade) ortak Stitch "Ozm Kids B2B Sipariş & Sevkiyat E-Postası"
 * token'ları. E-posta istemcileri için satır içi stil dizeleri olarak kullanılır; düzen
 * bagisto::emails.layouts.dealer içindedir.
 */
final class DealerMailTheme
{
    public const COLORS = [
        'background'  => '#fff8f5',
        'card'        => '#ffffff',
        'border'      => '#c1c8c9',
        'borderSoft'  => '#e1e5e6',
        'primary'     => '#2d4e55',
        'tertiary'    => '#404c3a',
        'secondary'   => '#44636b',
        'secondaryBg' => '#e4f2f6',
        'surfaceLow'  => '#fcf2ed',
        'outline'     => '#71787a',
        'ink'         => '#1f1b18',
        'inkSoft'     => '#41484a',
        'warning'     => '#8a5a00',
        'warningBg'   => '#fdf1dc',
        'danger'      => '#8c1d18',
        'dangerBg'    => '#fbe9e7',
    ];

    public const HEADING = "font-family:'Plus Jakarta Sans',Inter,Arial,sans-serif;";

    public const FONT = 'font-family:Inter,Arial,sans-serif;';

    public static function label(): string
    {
        return self::HEADING.'font-size:11px;line-height:16px;letter-spacing:0.08em;font-weight:600;text-transform:uppercase;';
    }

    public static function paragraph(): string
    {
        return 'margin:0;'.self::FONT.'font-size:14px;line-height:22px;color:'.self::COLORS['inkSoft'].';';
    }
}
