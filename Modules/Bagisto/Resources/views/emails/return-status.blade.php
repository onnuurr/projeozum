{{--
    Bayi iade bildirimi (onay, red, fatura reddi, para iadesi). Tasarım başvuru e-postalarıyla ortak düzende
    (bagisto::emails.layouts.dealer); renkler Modules\Bagisto\Mail\DealerMailTheme.

    Düzenlenebilen yalnızca $subject / $intro / $body (Bayi E-postaları › İade); rozet, iade özeti, fatura
    uyarısı, açıklama kutusu ve buton burada sabittir.
    Değişkenler: Modules\Bagisto\Mail\ReturnStatusMail::content()
--}}
@extends('bagisto::emails.layouts.dealer')

@php
    use Modules\Bagisto\Mail\DealerMailTheme;
    use Modules\Bagisto\Services\ReturnMailTemplateService as Stage;

    $c = DealerMailTheme::COLORS;
    $heading = DealerMailTheme::HEADING;
    $font = DealerMailTheme::FONT;
    $label = DealerMailTheme::label();
    $paragraph = DealerMailTheme::paragraph();

    // Adım: rozet, rozet tonu (secondary | danger | warning), açıklama kutusu başlığı.
    [$badge, $tone, $noteTitle] = match ($stage) {
        Stage::APPROVED, Stage::APPROVED_INVOICE => ['✓ İade Onaylandı', 'secondary', null],
        Stage::REJECTED => ['İade Reddedildi', 'danger', 'Değerlendirme Notu'],
        Stage::INVOICE_REJECTED => ['Fatura Kabul Edilmedi', 'warning', 'Fatura Kontrol Notu'],
        Stage::REFUNDED => ['✓ Ödeme Yapıldı', 'secondary', null],
        default => ['İade Güncellemesi', 'secondary', null],
    };

    [$badgeColor, $badgeBg] = match ($tone) {
        'danger' => [$c['danger'], $c['dangerBg']],
        'warning' => [$c['warning'], $c['warningBg']],
        default => [$c['secondary'], $c['secondaryBg']],
    };

    $rows = array_filter([
        'Sipariş' => $orderRef,
        'İade talebi' => '#'.$return->bagisto_rma_id,
        'Talep edilen tutar' => $stage === Stage::REJECTED ? Stage::money($return->requested_amount) : null,
        'Onaylanan tutar' => $amount ?: null,
        'İade yöntemi' => $stage === Stage::REFUNDED ? $refundMethodLabel : null,
        'İşlem no' => $stage === Stage::REFUNDED && $return->refund_method === 'bank' ? $return->refund_reference : null,
    ], fn ($value) => filled($value));

    $invoiceNeeded = in_array($stage, [Stage::APPROVED_INVOICE, Stage::INVOICE_REJECTED], true);
    $actionLabel = $invoiceNeeded ? 'İade Faturasını Yükle' : 'İade Talebini Görüntüle';

    $preheader = $intro;
    $pill = 'B2B Bayi İadeleri';
@endphp

@section('content')
    <span style="display:inline-block;{{ $label }}color:{{ $badgeColor }};background:{{ $badgeBg }};border:1px solid {{ $c['borderSoft'] }};border-radius:999px;padding:4px 14px;">
        {{ $badge }} • #{{ $return->bagisto_rma_id }}
    </span>

    <h1 style="margin:14px 0 10px;{{ $heading }}font-size:28px;line-height:36px;font-weight:600;letter-spacing:-0.015em;color:{{ $c['primary'] }};">
        Sayın {{ $name }},
    </h1>

    <p style="{{ $paragraph }}">{!! nl2br(e($intro)) !!}</p>

    {{-- İade özeti --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;border:1px solid {{ $c['border'] }};border-radius:8px;">
        <tr><td style="padding:16px;">
            <div style="{{ $label }}color:{{ $c['primary'] }};letter-spacing:0.04em;margin-bottom:10px;">İade Özeti</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="{{ $font }}font-size:13px;line-height:22px;">
                @foreach ($rows as $title => $value)
                    <tr>
                        <td style="color:{{ $c['outline'] }};{{ $loop->first ? '' : 'border-top:1px solid '.$c['borderSoft'].';' }}padding:4px 0;">{{ $title }}</td>
                        <td align="right" style="color:{{ $c['ink'] }};font-weight:500;{{ $loop->first ? '' : 'border-top:1px solid '.$c['borderSoft'].';' }}padding:4px 0;">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td></tr>
    </table>

    {{-- Fatura gerekli: yükleme uyarısı --}}
    @if ($invoiceNeeded)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;background:{{ $c['warningBg'] }};border:1px solid #f0d9a8;border-radius:8px;">
            <tr><td style="padding:14px 18px;">
                <div style="{{ $label }}color:{{ $c['warning'] }};letter-spacing:0.04em;margin-bottom:6px;">İade Faturası Bekleniyor</div>
                <div style="{{ $font }}font-size:13px;line-height:21px;color:{{ $c['ink'] }};">
                    Ödeme, iade faturanız bayi portalındaki iade sayfasından yüklenip kontrol edildikten sonra yapılır.
                </div>
            </td></tr>
        </table>
    @endif

    @if ($noteTitle && filled($note))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
            <tr><td style="padding:12px 16px;border-left:3px solid {{ $tone === 'danger' ? $c['danger'] : $c['border'] }};background:{{ $c['surfaceLow'] }};{{ $font }}font-size:13px;line-height:21px;color:{{ $c['inkSoft'] }};">
                <div style="{{ $label }}color:{{ $c['outline'] }};letter-spacing:0.04em;margin-bottom:4px;">{{ $noteTitle }}</div>
                {!! nl2br(e($note)) !!}
            </td></tr>
        </table>
    @endif

    @if (filled($body))
        <p style="{{ $paragraph }}margin-top:24px;">{!! nl2br(e($body)) !!}</p>
    @endif

    {{-- Ana aksiyon --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
        <tr>
            <td align="center" style="border-radius:8px;background:{{ $c['primary'] }};">
                <a href="{{ $returnUrl }}" style="display:block;padding:15px 24px;{{ $heading }}font-size:15px;line-height:20px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                    {{ $actionLabel }} &rarr;
                </a>
            </td>
        </tr>
    </table>
@endsection

@section('contact-lead', 'İade talebiniz, iade faturası veya ödemeniz hakkında sorularınız için bize ulaşabilirsiniz.')

@section('footer-note')
    Bu e-posta, {{ $companyName ? $companyName.' bayi hesabındaki' : 'bayi hesabınızdaki' }} iade talebi kapsamında otomatik olarak gönderilmiştir.
@endsection
