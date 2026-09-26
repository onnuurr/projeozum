{{--
    Bayi üyelik tamamlama daveti — Stitch "Ozm Kids B2B Sipariş & Sevkiyat E-Postası (Saf E-Posta Şablonu)"
    tasarım dilinin davete uyarlanmış hali. E-posta istemcileri için tablo tabanlı + satır içi stil;
    renk/tipografi Stitch token'larından (primary #2d4e55, surface #fff8f5 ...). Material ikonları
    e-postada güvenilir olmadığı için adım göstergesinde ✓ / numara kullanılır.
    Değişkenler: Modules\Bagisto\Mail\DealerInvitationMail::content()
--}}
@php
    $c = [
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
    ];

    $heading = "font-family:'Plus Jakarta Sans',Inter,Arial,sans-serif;";
    $body = 'font-family:Inter,Arial,sans-serif;';
    $label = $heading.'font-size:11px;line-height:16px;letter-spacing:0.08em;font-weight:600;text-transform:uppercase;';

    $model = ['dropshipping' => 'Dropshipping', 'toptan' => 'Toptan serili alım', 'hibrit' => 'Hibrit model'][$dealerModel ?? ''] ?? null;
    $expires = $application->invitation_expires_at?->timezone(config('app.timezone'))->locale('tr')->translatedFormat('j F Y');
    $name = $application->contact_person ?: $application->name;

    $steps = [
        ['done', 'Ön Başvuru', 'Tamamlandı'],
        ['done', 'Görüşme', 'Tamamlandı'],
        ['done', 'Resmi Evrak', 'Onaylandı'],
        ['current', 'Üyelik', 'Sizi bekliyor'],
    ];

    $todo = [
        'Bayi portalı parolanızı oluşturun (en az 8 karakter).',
        'Yetkili, telefon ve sevk adresi bilgilerinizi teyit edin.',
        'Kargo anlaşmanızı seçin: Ozm Kids anlaşması ya da kendi kargo anlaşmanız.',
        'Bayilik çerçeve sözleşmesinin son onayını verin.',
    ];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Bayi hesabınız açıldı — üyeliğinizi tamamlayın</title>
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
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $application->name }} için resmi evrak incelemesi tamamlandı; parolanızı oluşturup bayi portalına giriş yapabilirsiniz.</div>

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
                                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $c['tertiary'] }};vertical-align:middle;margin-right:6px;"></span>{{ $model ? 'B2B '.$model : 'B2B Bayi Hesabı' }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- İçerik --}}
                    <tr>
                        <td class="px" style="padding:32px 40px;">
                            <span style="display:inline-block;{{ $label }}color:{{ $c['secondary'] }};background:{{ $c['secondaryBg'] }};border:1px solid #cfe0e5;border-radius:999px;padding:4px 14px;">
                                ✓ Hesap Açıldı • #{{ $application->reference }}
                            </span>

                            <h1 style="margin:14px 0 10px;{{ $heading }}font-size:28px;line-height:36px;font-weight:600;letter-spacing:-0.015em;color:{{ $c['primary'] }};">
                                Sayın {{ $name }},
                            </h1>

                            <p style="margin:0;{{ $body }}font-size:14px;line-height:22px;color:{{ $c['inkSoft'] }};">
                                <strong style="color:{{ $c['ink'] }};font-weight:600;">{{ $application->name }}</strong> adına ilettiğiniz bayilik başvurusunun resmi evrak incelemesi tamamlandı ve bayi hesabınız açıldı. Aşağıdaki bağlantıyla parolanızı oluşturup birkaç bilgiyi teyit ettiğinizde bayi portalına giriş yaparak siparişe başlayabilirsiniz.
                            </p>

                            {{-- Adım göstergesi --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;background:{{ $c['surfaceLow'] }};border:1px solid {{ $c['borderSoft'] }};border-radius:8px;">
                                <tr>
                                    @foreach ($steps as $index => [$state, $title, $caption])
                                        <td width="25%" align="center" valign="top" style="padding:18px 4px;">
                                            @if ($state === 'done')
                                                <div style="width:28px;height:28px;line-height:28px;border-radius:50%;background:{{ $c['tertiary'] }};color:#ffffff;{{ $body }}font-size:14px;font-weight:600;margin:0 auto 6px;">✓</div>
                                            @else
                                                <div style="width:28px;height:28px;line-height:28px;border-radius:50%;background:{{ $c['primary'] }};color:#ffffff;{{ $body }}font-size:13px;font-weight:600;margin:0 auto 6px;box-shadow:0 0 0 4px rgba(45,78,85,0.2);">{{ $index + 1 }}</div>
                                            @endif
                                            <div style="{{ $label }}color:{{ $c['primary'] }};letter-spacing:0.04em;">{{ $title }}</div>
                                            <div style="{{ $body }}font-size:11px;line-height:16px;color:{{ $state === 'current' ? $c['primary'] : $c['outline'] }};{{ $state === 'current' ? 'font-weight:600;' : '' }}">{{ $caption }}</div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>

                            {{-- Başvuru + giriş bilgileri --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td class="stack" width="50%" valign="top" style="padding-right:8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $c['border'] }};border-radius:8px;">
                                            <tr><td style="padding:16px;">
                                                <div style="{{ $label }}color:{{ $c['primary'] }};letter-spacing:0.04em;margin-bottom:10px;">Başvuru Bilgileri</div>
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{{ $body }}font-size:12px;line-height:20px;">
                                                    <tr><td style="color:{{ $c['outline'] }};">Referans:</td><td align="right" style="color:{{ $c['ink'] }};font-weight:500;">#{{ $application->reference }}</td></tr>
                                                    <tr><td style="color:{{ $c['outline'] }};">Firma:</td><td align="right" style="color:{{ $c['ink'] }};">{{ $application->name }}</td></tr>
                                                    @if ($model)
                                                        <tr><td style="color:{{ $c['outline'] }};">Model:</td><td align="right" style="color:{{ $c['ink'] }};">{{ $model }}</td></tr>
                                                    @endif
                                                </table>
                                            </td></tr>
                                        </table>
                                    </td>
                                    <td class="stack stack-gap" width="50%" valign="top" style="padding-left:8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $c['border'] }};border-radius:8px;">
                                            <tr><td style="padding:16px;">
                                                <div style="{{ $label }}color:{{ $c['primary'] }};letter-spacing:0.04em;margin-bottom:10px;">Giriş Bilgileri</div>
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{{ $body }}font-size:12px;line-height:20px;">
                                                    <tr><td style="color:{{ $c['outline'] }};">Giriş e-postanız:</td></tr>
                                                    <tr><td style="color:{{ $c['primary'] }};font-weight:500;word-break:break-all;">{{ $application->contact_email }}</td></tr>
                                                    @if ($expires)
                                                        <tr><td style="padding-top:6px;border-top:1px solid {{ $c['borderSoft'] }};color:{{ $c['outline'] }};">Bağlantı geçerlilik:</td></tr>
                                                        <tr><td style="color:{{ $c['ink'] }};font-weight:500;">{{ $expires }}</td></tr>
                                                    @endif
                                                </table>
                                            </td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Üyelikte yapılacaklar --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                <tr>
                                    <td style="padding-bottom:6px;border-bottom:1px solid {{ $c['border'] }};">
                                        <span style="{{ $heading }}font-size:18px;line-height:26px;font-weight:600;color:{{ $c['primary'] }};">Üyeliği Tamamlarken</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:12px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['surfaceLow'] }};border:1px solid {{ $c['border'] }};border-radius:8px;">
                                            @foreach ($todo as $index => $item)
                                                <tr>
                                                    <td width="36" valign="top" style="padding:{{ $index ? '10px' : '16px' }} 0 {{ $loop->last ? '16px' : '0' }} 16px;">
                                                        <div style="width:22px;height:22px;line-height:22px;border-radius:50%;background:{{ $c['card'] }};border:1px solid {{ $c['border'] }};text-align:center;{{ $body }}font-size:11px;font-weight:600;color:{{ $c['primary'] }};">{{ $index + 1 }}</div>
                                                    </td>
                                                    <td valign="top" style="padding:{{ $index ? '10px' : '16px' }} 16px {{ $loop->last ? '16px' : '0' }} 10px;{{ $body }}font-size:13px;line-height:20px;color:{{ $c['inkSoft'] }};">
                                                        {{ $item }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Ana aksiyon --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                <tr>
                                    <td align="center" style="border-radius:8px;background:{{ $c['primary'] }};">
                                        <a href="{{ $invitationUrl }}" style="display:block;padding:15px 24px;{{ $heading }}font-size:15px;line-height:20px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                                            Üyeliğimi Tamamla &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top:14px;{{ $body }}font-size:11px;line-height:17px;color:{{ $c['outline'] }};word-break:break-all;">
                                        Buton çalışmazsa bu bağlantıyı tarayıcınıza yapıştırın:<br>
                                        <a href="{{ $invitationUrl }}" style="color:{{ $c['primary'] }};">{{ $invitationUrl }}</a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Danışman / destek --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;border:1px solid {{ $c['border'] }};border-radius:8px;">
                                <tr>
                                    <td width="56" valign="top" style="padding:16px 0 16px 16px;">
                                        <div style="width:40px;height:40px;line-height:40px;border-radius:50%;background:{{ $c['secondaryBg'] }};text-align:center;{{ $heading }}font-size:15px;font-weight:600;color:{{ $c['secondary'] }};">
                                            {{ $advisor['name'] ? mb_strtoupper(mb_substr($advisor['name'], 0, 1)) : '✉' }}
                                        </div>
                                    </td>
                                    <td valign="top" style="padding:16px 16px 16px 12px;">
                                        <div style="{{ $heading }}font-size:14px;line-height:20px;font-weight:600;color:{{ $c['primary'] }};">
                                            {{ $advisor['name'] ? 'B2B Portföy Danışmanınız' : 'B2B Bayi İlişkileri Masası' }}
                                        </div>
                                        <div style="margin-top:4px;{{ $body }}font-size:12px;line-height:18px;color:{{ $c['inkSoft'] }};">Üyelik adımları, kargo anlaşması veya ilk siparişiniz hakkında sorularınız için bize ulaşabilirsiniz.</div>
                                        <div style="margin-top:8px;{{ $body }}font-size:12px;line-height:18px;">
                                            @if ($advisor['name'])
                                                <span style="color:{{ $c['ink'] }};font-weight:500;">{{ $advisor['name'] }}</span>
                                                @if ($advisor['title']) <span style="color:{{ $c['outline'] }};"> · {{ $advisor['title'] }}</span> @endif
                                            @endif
                                            @if ($advisor['phone'])
                                                &nbsp;&nbsp;<a href="tel:{{ preg_replace('/[^0-9+]/', '', $advisor['phone']) }}" style="color:{{ $c['primary'] }};font-weight:500;text-decoration:none;">{{ $advisor['phone'] }}</a>
                                            @endif
                                            @if ($advisor['whatsapp'])
                                                &nbsp;&nbsp;<a href="https://wa.me/{{ $advisor['whatsapp'] }}" style="color:{{ $c['tertiary'] }};font-weight:500;text-decoration:none;">WhatsApp Destek</a>
                                            @endif
                                            @if (! $advisor['phone'] && ! $advisor['whatsapp'] && $supportEmail)
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
                        <td class="px" align="center" style="padding:24px 40px;background:{{ $c['surfaceLow'] }};border-top:1px solid {{ $c['border'] }};{{ $body }}color:{{ $c['outline'] }};">
                            <div style="font-size:12px;line-height:18px;">Ozm Kids · Bomonti Atölye &amp; B2B Bayi Portalı</div>
                            <div style="margin-top:8px;font-size:11px;line-height:17px;">
                                Bu e-posta, {{ $application->name }} adına yapılan B2B bayilik başvurusu kapsamında otomatik olarak gönderilmiştir. Bağlantı size özeldir; kimseyle paylaşmayın.
                            </div>
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
