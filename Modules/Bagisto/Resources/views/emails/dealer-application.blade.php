{{--
    Bayi portalı başvuru e-postalarının tek (standart) şablonu — Stitch "Ozm Kids B2B Sipariş & Sevkiyat
    E-Postası (Saf E-Posta Şablonu)" tasarım dili; renkler Modules\Bagisto\Mail\DealerMailTheme. Material ikonları
    e-postada güvenilir olmadığı için adım göstergesinde ✓ / numara kullanılır.

    Düzenlenebilen yalnızca $subject / $intro / $body (Bayi E-postaları › Başvuru); rozet, adım
    göstergesi, adıma özel bloklar ve buton burada sabittir. Başlık, iletişim kartı ve alt bilgi ortak
    düzendedir (bagisto::emails.layouts.dealer).
    Değişkenler: Modules\Bagisto\Mail\DealerApplicationMail::content()
--}}
@extends('bagisto::emails.layouts.dealer')

@php
    use Modules\Bagisto\Mail\DealerMailTheme;

    $c = DealerMailTheme::COLORS;
    $heading = DealerMailTheme::HEADING;
    $font = DealerMailTheme::FONT;
    $label = DealerMailTheme::label();
    $paragraph = DealerMailTheme::paragraph();

    $model = ['dropshipping' => 'Dropshipping', 'toptan' => 'Toptan serili alım', 'hibrit' => 'Hibrit model'][$dealerModel ?? ''] ?? null;

    // Adım: rozet, adım göstergesindeki konum (null = gösterge yok), buton.
    [$badge, $current, $caption, $actionUrl, $actionLabel] = match ($stage) {
        'received'            => ['Başvuru Alındı', 1, 'Planlanıyor', $statusUrl, 'Başvuru Durumunu Gör'],
        'documents_requested' => ['Resmi Belge Adımı', 2, 'Sizi bekliyor', $statusUrl, 'Belgeleri Yükle'],
        'documents_reminder'  => ['Hatırlatma', 2, 'Sizi bekliyor', $statusUrl, 'Belgeleri Yükle'],
        'missing_documents'   => ['Ek Belge Talebi', 2, 'Ek belge bekleniyor', $statusUrl, 'Başvuru Sayfasına Git'],
        'account_opened'      => ['✓ Hesap Açıldı', 3, 'Sizi bekliyor', $invitationUrl, 'Üyeliğimi Tamamla'],
        default               => ['Başvuru Sonucu', null, null, null, null],
    };

    $steps = ['Ön Başvuru', 'Görüşme', 'Resmi Evrak', 'Üyelik'];

    $todo = [
        'Bayi portalı parolanızı oluşturun (en az 8 karakter).',
        'Yetkili, telefon ve sevk adresi bilgilerinizi teyit edin.',
        'Kargo anlaşmanızı seçin: Ozm Kids anlaşması ya da kendi kargo anlaşmanız.',
        'Bayilik çerçeve sözleşmesinin son onayını verin.',
    ];

    $preheader = $intro;
    $pill = $model ? 'B2B '.$model : 'B2B Bayilik Başvurusu';
@endphp

@section('content')
    <span style="display:inline-block;{{ $label }}color:{{ $c['secondary'] }};background:{{ $c['secondaryBg'] }};border:1px solid #cfe0e5;border-radius:999px;padding:4px 14px;">
        {{ $badge }} • #{{ $application->reference }}
    </span>

    <h1 style="margin:14px 0 10px;{{ $heading }}font-size:28px;line-height:36px;font-weight:600;letter-spacing:-0.015em;color:{{ $c['primary'] }};">
        Sayın {{ $name }},
    </h1>

    <p style="{{ $paragraph }}">{!! nl2br(e($intro)) !!}</p>

    {{-- Ek belge talebi: istenen belgeler + not --}}
    @if ($documents)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background:{{ $c['warningBg'] }};border:1px solid #f0d9a8;border-radius:8px;">
            <tr><td style="padding:16px 18px;">
                <div style="{{ $label }}color:{{ $c['warning'] }};letter-spacing:0.04em;margin-bottom:8px;">İstenen Belgeler</div>
                @foreach ($documents as $document)
                    <div style="{{ $font }}font-size:13px;line-height:22px;color:{{ $c['ink'] }};">• {{ $document }}</div>
                @endforeach
            </td></tr>
        </table>
    @endif

    @if ($note || $reason)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
            <tr><td style="padding:12px 16px;border-left:3px solid {{ $c['border'] }};background:{{ $c['surfaceLow'] }};{{ $font }}font-size:13px;line-height:21px;color:{{ $c['inkSoft'] }};">
                <div style="{{ $label }}color:{{ $c['outline'] }};letter-spacing:0.04em;margin-bottom:4px;">{{ $reason ? 'Değerlendirme Notu' : 'Ekibimizin Notu' }}</div>
                {!! nl2br(e($reason ?: $note)) !!}
            </td></tr>
        </table>
    @endif

    {{-- Adım göstergesi --}}
    @if ($current !== null)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;background:{{ $c['surfaceLow'] }};border:1px solid {{ $c['borderSoft'] }};border-radius:8px;">
            <tr>
                @foreach ($steps as $index => $title)
                    <td width="25%" align="center" valign="top" style="padding:18px 4px;">
                        @if ($index < $current)
                            <div style="width:28px;height:28px;line-height:28px;border-radius:50%;background:{{ $c['tertiary'] }};color:#ffffff;{{ $font }}font-size:14px;font-weight:600;margin:0 auto 6px;">✓</div>
                        @elseif ($index === $current)
                            <div style="width:28px;height:28px;line-height:28px;border-radius:50%;background:{{ $c['primary'] }};color:#ffffff;{{ $font }}font-size:13px;font-weight:600;margin:0 auto 6px;box-shadow:0 0 0 4px rgba(45,78,85,0.2);">{{ $index + 1 }}</div>
                        @else
                            <div style="width:26px;height:26px;line-height:26px;border-radius:50%;background:{{ $c['card'] }};border:1px solid {{ $c['border'] }};color:{{ $c['outline'] }};{{ $font }}font-size:13px;font-weight:600;margin:0 auto 6px;">{{ $index + 1 }}</div>
                        @endif
                        <div style="{{ $label }}color:{{ $index > $current ? $c['outline'] : $c['primary'] }};letter-spacing:0.04em;">{{ $title }}</div>
                        <div style="{{ $font }}font-size:11px;line-height:16px;color:{{ $index === $current ? $c['primary'] : $c['outline'] }};{{ $index === $current ? 'font-weight:600;' : '' }}">
                            {{ $index < $current ? 'Tamamlandı' : ($index === $current ? $caption : 'Sırada') }}
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    {{-- Hesap açıldı: başvuru + giriş bilgileri, üyelikte yapılacaklar --}}
    @if ($stage === 'account_opened')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
            <tr>
                <td class="stack" width="50%" valign="top" style="padding-right:8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $c['border'] }};border-radius:8px;">
                        <tr><td style="padding:16px;">
                            <div style="{{ $label }}color:{{ $c['primary'] }};letter-spacing:0.04em;margin-bottom:10px;">Başvuru Bilgileri</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{{ $font }}font-size:12px;line-height:20px;">
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
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{{ $font }}font-size:12px;line-height:20px;">
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
                                    <div style="width:22px;height:22px;line-height:22px;border-radius:50%;background:{{ $c['card'] }};border:1px solid {{ $c['border'] }};text-align:center;{{ $font }}font-size:11px;font-weight:600;color:{{ $c['primary'] }};">{{ $index + 1 }}</div>
                                </td>
                                <td valign="top" style="padding:{{ $index ? '10px' : '16px' }} 16px {{ $loop->last ? '16px' : '0' }} 10px;{{ $font }}font-size:13px;line-height:20px;color:{{ $c['inkSoft'] }};">
                                    {{ $item }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if (filled($body))
        <p style="{{ $paragraph }}margin-top:24px;">{!! nl2br(e($body)) !!}</p>
    @endif

    {{-- Ana aksiyon --}}
    @if ($actionUrl)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
            <tr>
                <td align="center" style="border-radius:8px;background:{{ $c['primary'] }};">
                    <a href="{{ $actionUrl }}" style="display:block;padding:15px 24px;{{ $heading }}font-size:15px;line-height:20px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                        {{ $actionLabel }} &rarr;
                    </a>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding-top:14px;{{ $font }}font-size:11px;line-height:17px;color:{{ $c['outline'] }};word-break:break-all;">
                    Buton çalışmazsa bu bağlantıyı tarayıcınıza yapıştırın:<br>
                    <a href="{{ $actionUrl }}" style="color:{{ $c['primary'] }};">{{ $actionUrl }}</a>
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('contact-lead', 'Başvurunuz, üyelik adımları veya ilk siparişiniz hakkında sorularınız için bize ulaşabilirsiniz.')

@section('footer-note')
    Bu e-posta, {{ $application->name }} adına yapılan B2B bayilik başvurusu kapsamında otomatik olarak gönderilmiştir.@if ($actionUrl) Bağlantı size özeldir; kimseyle paylaşmayın.@endif
@endsection
