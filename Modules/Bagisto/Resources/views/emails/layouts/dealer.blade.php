{{--
    Bayi e-postalarının (başvuru adımları, iade bildirimleri) ortak düzeni — Stitch "Ozm Kids B2B Sipariş &
    Sevkiyat E-Postası (Saf E-Posta Şablonu)" tasarım dili. E-posta istemcileri için tablo tabanlı + satır içi
    stil; renk/tipografi Modules\Bagisto\Mail\DealerMailTheme'den.

    Beklenen değişkenler: $subject, $preheader, $pill (başlıktaki rozet), $contact (DealerMailContactService),
    $portalUrl. Bölümler: content (zorunlu), contact-lead (iletişim kartı açıklaması), footer-note.
--}}
@php
    use Modules\Bagisto\Mail\DealerMailTheme;

    $c = DealerMailTheme::COLORS;
    $heading = DealerMailTheme::HEADING;
    $font = DealerMailTheme::FONT;
    $label = DealerMailTheme::label();

    $advisorName = $contact['advisor_name'] ?? null;
    $supportEmail = $contact['support_email'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600&display=swap" rel="stylesheet">
    <style>
        @media only screen and (max-width: 620px) {
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .stack { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; text-align: left !important; }
            .stack-gap { padding-top: 12px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $c['background'] }};">
    {{-- Önizleme metni --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \Illuminate\Support\Str::limit($preheader, 140) }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['background'] }};">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:{{ $c['card'] }};border:1px solid {{ $c['border'] }};border-radius:12px;overflow:hidden;">
                    {{-- Üst çizgi + başlık --}}
                    <tr><td style="height:6px;background:{{ $c['primary'] }};font-size:0;line-height:0;">&nbsp;</td></tr>
                    <tr>
                        <td class="px" style="padding:32px 40px 24px;border-bottom:1px solid {{ $c['border'] }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="stack" valign="middle">
                                        <div style="{{ $heading }}font-size:22px;line-height:24px;font-weight:600;letter-spacing:-0.01em;color:{{ $c['primary'] }};">ozm kids</div>
                                        <div style="{{ $label }}color:{{ $c['outline'] }};margin-top:4px;">Bomonti Atölye &amp; B2B Bayi Portalı</div>
                                    </td>
                                    <td class="stack stack-gap" valign="middle" align="right">
                                        <span style="display:inline-block;{{ $label }}color:{{ $c['primary'] }};background:{{ $c['surfaceLow'] }};border:1px solid {{ $c['border'] }};border-radius:999px;padding:6px 12px;white-space:nowrap;">
                                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $c['tertiary'] }};vertical-align:middle;margin-right:6px;"></span>{{ $pill }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- İçerik --}}
                    <tr>
                        <td class="px" style="padding:32px 40px;">
                            @yield('content')

                            {{-- Danışman / destek --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;border:1px solid {{ $c['border'] }};border-radius:8px;">
                                <tr>
                                    <td width="56" valign="top" style="padding:16px 0 16px 16px;">
                                        <div style="width:40px;height:40px;line-height:40px;border-radius:50%;background:{{ $c['secondaryBg'] }};text-align:center;{{ $heading }}font-size:15px;font-weight:600;color:{{ $c['secondary'] }};">
                                            {{ $advisorName ? mb_strtoupper(mb_substr($advisorName, 0, 1)) : '✉' }}
                                        </div>
                                    </td>
                                    <td valign="top" style="padding:16px 16px 16px 12px;">
                                        <div style="{{ $heading }}font-size:14px;line-height:20px;font-weight:600;color:{{ $c['primary'] }};">
                                            {{ $advisorName ? 'B2B Portföy Danışmanınız' : 'B2B Bayi İlişkileri Masası' }}
                                        </div>
                                        <div style="margin-top:4px;{{ $font }}font-size:12px;line-height:18px;color:{{ $c['inkSoft'] }};">@yield('contact-lead', 'Sorularınız için bize ulaşabilirsiniz.')</div>
                                        <div style="margin-top:8px;{{ $font }}font-size:12px;line-height:18px;">
                                            @if ($advisorName)
                                                <span style="color:{{ $c['ink'] }};font-weight:500;">{{ $advisorName }}</span>
                                                @if ($contact['advisor_title']) <span style="color:{{ $c['outline'] }};"> · {{ $contact['advisor_title'] }}</span> @endif
                                            @endif
                                            @if ($contact['advisor_phone'])
                                                &nbsp;&nbsp;<a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['advisor_phone']) }}" style="color:{{ $c['primary'] }};font-weight:500;text-decoration:none;">{{ $contact['advisor_phone'] }}</a>
                                            @endif
                                            @if ($contact['advisor_whatsapp'])
                                                &nbsp;&nbsp;<a href="https://wa.me/{{ $contact['advisor_whatsapp'] }}" style="color:{{ $c['tertiary'] }};font-weight:500;text-decoration:none;">WhatsApp Destek</a>
                                            @endif
                                            @if (! $contact['advisor_phone'] && ! $contact['advisor_whatsapp'] && $supportEmail)
                                                <a href="mailto:{{ $supportEmail }}" style="color:{{ $c['primary'] }};font-weight:500;text-decoration:none;">{{ $supportEmail }}</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Alt bilgi --}}
                    <tr>
                        <td class="px" align="center" style="padding:24px 40px;background:{{ $c['surfaceLow'] }};border-top:1px solid {{ $c['border'] }};{{ $font }}color:{{ $c['outline'] }};">
                            <div style="font-size:12px;line-height:18px;">Ozm Kids · Bomonti Atölye &amp; B2B Bayi Portalı</div>
                            <div style="margin-top:8px;font-size:11px;line-height:17px;">@yield('footer-note')</div>
                            <div style="margin-top:12px;font-size:12px;line-height:18px;">
                                <a href="{{ $portalUrl }}" style="color:{{ $c['outline'] }};text-decoration:none;">Bayi Portalı</a>
                                @if ($supportEmail)
                                    &nbsp;•&nbsp; <a href="mailto:{{ $supportEmail }}" style="color:{{ $c['outline'] }};text-decoration:none;">B2B Destek</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
