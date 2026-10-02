{{--
    Bayi sözleşmesi onay kopyası (İş Ortağı Başvuruları › başvuru detayı › "Onaylanan sözleşmeler").
    Metin, bayinin onay anında yayımlanmış sürümün bayi bilgileriyle doldurulmuş hâlidir
    (dealer_agreement_acceptances.html — sonradan değişmez). Tarayıcıdan yazdırılır / PDF kaydedilir.
--}}
@php
    /** @var \Modules\Bagisto\Models\DealerAgreementAcceptance $acceptance */
    $vars = $acceptance->data['variables'] ?? [];
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))->locale('tr')->translatedFormat('j F Y, H:i') : '—';

    $seller = array_filter([
        'Ticari unvan' => $vars['seller.legal_name'] ?? null,
        'VKN' => $vars['seller.tax_number'] ?? null,
        'Vergi dairesi' => $vars['seller.tax_office'] ?? null,
        'MERSİS' => $vars['seller.mersis'] ?? null,
        'Adres' => $vars['seller.address'] ?? null,
        'KEP' => $vars['seller.kep'] ?? null,
    ]);

    $rows = array_filter([
        'Ticari unvan' => $vars['company.legal_name'] ?? null,
        'VKN / TCKN' => $vars['company.tax_number'] ?? null,
        'Vergi dairesi' => $vars['company.tax_office'] ?? null,
        'Adres' => $vars['company.address'] ?? null,
        'Yetkili' => $vars['user.name'] ?? null,
        'E-posta' => $vars['user.email'] ?? null,
        'Telefon' => $vars['user.phone'] ?? null,
        'Başvuru referansı' => $vars['application.reference'] ?? null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $acceptance->title }} · {{ $vars['company.name'] ?? '' }} · {{ $acceptance->version }}</title>
    <style>
        :root { --ink: #1f2933; --soft: #52606d; --muted: #7b8794; --line: #e4e7eb; --bg: #f5f7fa; --brand: #45666D; }
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 11.5px/1.55 "Helvetica Neue", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { max-width: 190mm; margin: 20px auto 0; display: flex; justify-content: flex-end; }
        .toolbar button { background: var(--brand); color: #fff; border: 0; border-radius: 8px; padding: 9px 16px; font: 600 12.5px/1 inherit; cursor: pointer; }
        .page { max-width: 190mm; margin: 12px auto 20px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 26px 28px; }

        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--brand); }
        .eyebrow { font-size: 9.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
        h1 { margin: 4px 0 2px; font-size: 19px; line-height: 1.25; color: var(--brand); }
        .ref { text-align: right; white-space: nowrap; }
        .ref strong { display: block; font-size: 15px; margin-top: 2px; }

        .parties { margin-top: 14px; border: 1px solid var(--line); border-radius: 9px; padding: 9px 12px 5px; break-inside: avoid; }
        h2 { margin: 0 0 4px; font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
        .rows { display: grid; grid-template-columns: 1fr 1fr; column-gap: 18px; }
        .row { display: grid; grid-template-columns: 38% 1fr; gap: 8px; padding: 2.5px 0; border-top: 1px solid var(--line); }
        .row span { color: var(--muted); }
        .row b { font-weight: 600; word-break: break-word; }

        .body { margin-top: 16px; font-size: 12px; }
        .body p { margin: 0 0 9px; }
        .body ul, .body ol { margin: 0 0 9px; padding-left: 20px; }
        .body h1, .body h2, .body h3, .body h4 { margin: 14px 0 6px; font-size: 13px; letter-spacing: 0; text-transform: none; color: var(--ink); }
        .body h2 { font-size: 14px; }
        .body blockquote { margin: 0 0 9px; padding: 6px 12px; border-left: 3px solid var(--brand); background: var(--bg); }
        .body hr { border: 0; border-top: 1px solid var(--line); margin: 12px 0; }
        .body a { color: var(--brand); }
        .body table { width: 100%; border-collapse: collapse; margin: 0 0 10px; break-inside: avoid; }
        .body th, .body td { border: 1px solid var(--line); padding: 5px 7px; text-align: left; vertical-align: top; }
        .body th { background: var(--bg); font-weight: 600; }
        .body th p, .body td p { margin: 0; }
        /* Ekler: her biri yeni sayfadan başlar. */
        .body .agreement-annex { break-before: page; padding-top: 4px; }
        .body .agreement-annex > h2 { margin: 0 0 10px; padding-bottom: 6px; border-bottom: 2px solid var(--brand); font-size: 15px; color: var(--brand); }
        @media screen { .body .agreement-annex { margin-top: 26px; padding-top: 18px; border-top: 1px dashed var(--line); } }

        .consent { margin-top: 18px; padding: 9px 12px; border-left: 3px solid var(--brand); background: var(--bg); border-radius: 0 8px 8px 0; break-inside: avoid; }
        .consent b { display: block; font-size: 9.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 2px; }
        footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; color: var(--muted); font-size: 9.5px; }

        @media (max-width: 640px) { .rows { grid-template-columns: 1fr; } header { flex-direction: column; } .ref { text-align: left; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; max-width: none; border: 0; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Yazdır / PDF olarak kaydet</button>
    </div>

    <main class="page">
        <header>
            <div>
                <div class="eyebrow">Bayi sözleşmesi onay kopyası · {{ $contextLabel }}</div>
                <h1>{{ $acceptance->title }}</h1>
            </div>
            <div class="ref">
                <div class="eyebrow">Sürüm</div>
                <strong>{{ $acceptance->version }}</strong>
            </div>
        </header>

        @if ($seller)
            <section class="parties">
                <h2>Satıcı</h2>
                <div class="rows">
                    @foreach ($seller as $key => $value)
                        <div class="row"><span>{{ $key }}</span><b>{{ $value }}</b></div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($rows)
            <section class="parties">
                <h2>Bayi</h2>
                <div class="rows">
                    @foreach ($rows as $key => $value)
                        <div class="row"><span>{{ $key }}</span><b>{{ $value }}</b></div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Onay anında SaaS'ta Markdown'dan üretilmiş HTML (ham HTML atılmış). --}}
        <div class="body">{!! $acceptance->html !!}</div>

        <div class="consent">
            <b>Elektronik onay</b>
            {{ $vars['user.name'] ?? 'Bayi yetkilisi' }}, bu metni bayi portalında {{ $date($acceptance->accepted_at) }} tarihinde onay kutusunu işaretleyerek kabul etmiştir{{ $acceptance->ip_address ? ' (IP: '.$acceptance->ip_address.')' : '' }}.
        </div>

        <footer>
            <span>Kayıt no: {{ $acceptance->id }} · {{ $acceptance->agreement_key }}</span>
            <span>Çıktı: {{ $date(now()) }}</span>
        </footer>
    </main>
</body>
</html>
