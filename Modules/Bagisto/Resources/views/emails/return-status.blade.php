{{--
    Bayi iade bildirimi. Dealer-invitation e-postasının Stitch token'ları + tablo tabanlı, satır içi stil düzeni.
    Değişkenler: Modules\Bagisto\Mail\ReturnStatusMail::content()
--}}
@php
    use Modules\Bagisto\Jobs\NotifyTenantOfReturn;

    $c = [
        'background' => '#fff8f5',
        'card' => '#ffffff',
        'border' => '#c1c8c9',
        'primary' => '#2d4e55',
        'surfaceLow' => '#fcf2ed',
        'outline' => '#71787a',
        'ink' => '#1f1b18',
        'inkSoft' => '#41484a',
        'danger' => '#8c1d18',
    ];

    $heading = "font-family:'Plus Jakarta Sans',Inter,Arial,sans-serif;";
    $body = 'font-family:Inter,Arial,sans-serif;';
    $amount = number_format((float) $return->approved_amount, 2, ',', '.').' ₺';

    [$title, $lead] = match ($event) {
        NotifyTenantOfReturn::EVENT_APPROVED => [
            'İade talebiniz onaylandı',
            $return->invoice_required
                ? "İade tutarı {$amount} olarak onaylandı. Sipariş tarafımızca faturalandırıldığı için ödemenin yapılabilmesi adına iade faturanızı bayi portalındaki iade sayfasından yüklemeniz gerekiyor."
                : "İade tutarı {$amount} olarak onaylandı. Ödemeniz, siparişin ödeme yöntemine en kısa sürede yapılacaktır.",
        ],
        NotifyTenantOfReturn::EVENT_REJECTED => [
            'İade talebiniz reddedildi',
            'İade talebiniz incelendi ve kabul edilmedi. Gerekçeyi aşağıda bulabilirsiniz.',
        ],
        NotifyTenantOfReturn::EVENT_INVOICE_REJECTED => [
            'İade faturanız kabul edilmedi',
            'Yüklediğiniz iade faturası kontrol edildi ve kabul edilmedi. Açıklamayı inceleyip bayi portalından düzeltilmiş faturayı yeniden yükleyebilirsiniz.',
        ],
        NotifyTenantOfReturn::EVENT_REFUNDED => [
            'İade ödemeniz yapıldı',
            "{$amount} tutarındaki iade ödemeniz yapıldı".($refundMethodLabel ? " ({$refundMethodLabel})" : '').'.',
        ],
        default => ['İade talebinizde güncelleme', 'İade talebinizin durumu güncellendi.'],
    };

    $note = match ($event) {
        NotifyTenantOfReturn::EVENT_REJECTED => $return->decision_note,
        NotifyTenantOfReturn::EVENT_INVOICE_REJECTED => $return->invoice_review_note,
        default => $return->decision_note,
    };
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $c['background'] }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['background'] }};">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:{{ $c['card'] }};border:1px solid {{ $c['border'] }};border-radius:12px;">
                <tr>
                    <td style="padding:32px 40px 8px;">
                        <p style="{{ $body }}margin:0 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:{{ $c['outline'] }};">Ozm Kids · Bayi İadeleri</p>
                        <h1 style="{{ $heading }}margin:0;font-size:22px;line-height:30px;color:{{ $event === NotifyTenantOfReturn::EVENT_REJECTED ? $c['danger'] : $c['primary'] }};">{{ $title }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 40px 0;{{ $body }}font-size:15px;line-height:24px;color:{{ $c['ink'] }};">
                        <p style="margin:0 0 12px;">Merhaba {{ $tenantName }},</p>
                        <p style="margin:0 0 16px;">{{ $lead }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 40px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['surfaceLow'] }};border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px;{{ $body }}font-size:14px;line-height:22px;color:{{ $c['inkSoft'] }};">
                                    <strong style="color:{{ $c['ink'] }};">Sipariş:</strong> {{ $orderRef }}<br>
                                    <strong style="color:{{ $c['ink'] }};">İade talebi:</strong> #{{ $return->bagisto_rma_id }}
                                    @if ($return->approved_amount !== null)
                                        <br><strong style="color:{{ $c['ink'] }};">Onaylanan tutar:</strong> {{ $amount }}
                                    @endif
                                    @if ($return->refund_reference && $event === NotifyTenantOfReturn::EVENT_REFUNDED && $return->refund_method === 'bank')
                                        <br><strong style="color:{{ $c['ink'] }};">İşlem no:</strong> {{ $return->refund_reference }}
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if (filled($note))
                    <tr>
                        <td style="padding:16px 40px 0;{{ $body }}font-size:14px;line-height:22px;color:{{ $c['ink'] }};">
                            <strong>Açıklama:</strong><br>{!! nl2br(e($note)) !!}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:24px 40px 32px;">
                        <a href="{{ $portalUrl }}" style="{{ $heading }}display:inline-block;background:{{ $c['primary'] }};color:#ffffff;text-decoration:none;font-size:14px;padding:12px 22px;border-radius:8px;">İade talebini görüntüle</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
