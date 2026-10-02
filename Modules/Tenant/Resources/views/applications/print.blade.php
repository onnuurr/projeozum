{{--
    İş ortağı başvuru dosyası (panel › İş Ortağı Başvuruları › "Başvuru dosyasını yazdır").
    Dosyalamaya uygun, iç kullanım çıktısı: tam IBAN, inceleyen, IP ve açılan iş ortağı hesabı dahil.
    1. sayfa başvuru dökümü; görsel belgeler (jpg/png) ayrı sayfalarda "Ek" olarak basılır, PDF belgeler
    listede yer alır (panelde önizleyip ayrıca yazdırılır).
--}}
@php
    use Modules\Tenant\Models\TenantApplication;
    use Modules\Tenant\Models\TenantApplicationDocument;

    /** @var TenantApplication $application */
    $a = $application;
    $details = $a->details ?? [];
    $consents = $details['consents'] ?? [];
    $tenant = $a->convertedTenant;
    $portal = $a->isFromPortal();

    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))->locale('tr')->translatedFormat('j F Y, H:i') : null;
    $money = fn ($value) => $value === null ? null : number_format((float) $value, 2, ',', '.').' TL';

    $labels = [
        'company_type' => ['sahis' => 'Şahıs şirketi', 'limited' => 'Limited şirketi', 'anonim' => 'Anonim şirketi'],
        'dealer_model' => ['dropshipping' => 'Dropshipping', 'toptan' => 'Toptan serili alım', 'hibrit' => 'Hibrit'],
        'channel' => ['fiziki' => 'Fiziki butik', 'instagram' => 'Instagram / sosyal medya', 'web' => 'Kendi e-ticaret sitesi', 'pazaryeri' => 'Pazaryeri'],
        'payment_preference' => ['kart' => 'Kredi kartı / 3D Secure', 'havale' => 'Havale / EFT cari bakiye'],
        'shipping' => ['platform' => 'Ozm Kids kargo anlaşması', 'own' => 'Kendi kargo anlaşması'],
    ];
    $label = fn (string $group, $key) => $key === null ? null : ($labels[$group][$key] ?? $key);

    $statusLabel = match (true) {
        $a->status === TenantApplication::STATUS_APPROVED && $portal => $a->completed_at ? 'Üyelik tamamlandı' : 'Hesap açıldı · üyelik bekleniyor',
        default => [
            TenantApplication::STATUS_PENDING => $portal ? 'Ön başvuru' : 'Beklemede',
            TenantApplication::STATUS_DOCUMENTS_REQUESTED => 'Resmi belge bekleniyor',
            TenantApplication::STATUS_DOCUMENTS_REVIEW => 'Evrak incelemesi',
            TenantApplication::STATUS_MISSING_DOCUMENTS => 'Eksik evrak bekleniyor',
            TenantApplication::STATUS_APPROVED => 'Onaylandı',
            TenantApplication::STATUS_REJECTED => 'Reddedildi',
        ][$a->status] ?? $a->status,
    };
    $statusTone = match ($a->status) {
        TenantApplication::STATUS_APPROVED => 'success',
        TenantApplication::STATUS_REJECTED => 'danger',
        default => 'warning',
    };

    // Boş satırlar basılmaz.
    $cards = array_filter([
        'Firma' => array_filter([
            'Ticari unvan' => $a->legal_name,
            'Marka / mağaza adı' => $a->name,
            'Şirket türü' => $label('company_type', $details['company_type'] ?? null),
            'VKN / TCKN' => $a->tax_number,
            'Vergi dairesi' => $a->tax_office,
            'Adres' => trim(collect([$a->address, collect([$a->district, $a->city])->filter()->implode(' / '), $a->postal_code])->filter()->implode(', ')),
        ]),
        'Yetkili ve iletişim' => array_filter([
            'Yetkili' => $a->contact_person,
            'Telefon' => $a->contact_phone,
            'E-posta' => $a->contact_email,
        ]),
        'Bayilik modeli' => array_filter([
            'Model' => $label('dealer_model', $details['dealer_model'] ?? null),
            'Aylık hacim' => isset($details['monthly_volume']) ? $details['monthly_volume'].' adet / ay' : null,
            'Satış kanalları' => collect($details['channels'] ?? [])
                ->map(fn ($c) => $label('channel', $c['type']).(! empty($c['value']) ? ': '.$c['value'] : ''))
                ->implode(' · '),
        ]),
        'Ticari beyan ve banka' => array_filter([
            'Ciro hedefi' => isset($details['revenue_target']) ? $details['revenue_target'].' bin TL / ay' : null,
            'Ödeme tercihi' => $label('payment_preference', $details['payment_preference'] ?? null),
            'Banka' => $details['bank']['bank_name'] ?? null,
            'Hesap sahibi' => $details['bank']['account_holder'] ?? null,
            'IBAN' => isset($details['bank']['iban']) ? trim(chunk_split($details['bank']['iban'], 4, ' ')) : null,
        ]),
        'Açılan iş ortağı hesabı' => $tenant ? array_filter([
            'İş ortağı kodu' => $tenant->code,
            'İş ortağı tipi' => $tenant->type?->name,
            'Kredi limiti' => $money($tenant->credit_limit),
            'İskonto oranı' => $tenant->discount_rate !== null ? '%'.rtrim(rtrim(number_format((float) $tenant->discount_rate, 2, ',', '.'), '0'), ',') : null,
            'Kargo anlaşması' => $label('shipping', $tenant->shipping_agreement_type).($tenant->carrier ? ' · '.$tenant->carrier->name : ''),
            'Hesap durumu' => $tenant->is_active ? 'Aktif' : 'Pasif (üyelik tamamlanınca aktifleşir)',
        ], fn ($v) => filled($v)) : [],
    ]);

    $consentRows = array_filter([
        'Taahhütname' => ! empty($consents['declaration']) ? 'Onaylandı'.(! empty($consents['declaration_version']) ? ' · Sürüm '.$consents['declaration_version'] : '').' · '.($date($consents['accepted_at'] ?? null) ?? '—') : null,
        'Ticari ileti izni' => $portal ? (! empty($consents['marketing']) ? 'İzin verildi'.(! empty($consents['marketing_version']) ? ' · Sürüm '.$consents['marketing_version'] : '') : 'İzin verilmedi') : null,
        'Çerçeve sözleşme' => ! empty($consents['contract']) ? 'Sürüm '.($consents['contract_version'] ?? '—').' · '.($date($consents['contract_accepted_at'] ?? null) ?? '—') : null,
        'Platform kargo şartları' => ! empty($consents['shipping_terms_version']) ? 'Sürüm '.$consents['shipping_terms_version'] : null,
        'Üyelik son onayı' => ! empty($consents['membership_accepted_at']) ? 'Sürüm '.($consents['membership_contract_version'] ?? '—').' · '.$date($consents['membership_accepted_at']) : null,
    ]);

    $timeline = array_filter([
        'Başvuru alındı' => $date($a->created_at),
        'Belge adımı açıldı' => $date($a->documents_requested_at),
        'Belge hatırlatması' => $date($a->documents_reminded_at),
        'Belgeler iletildi' => $date($a->documents_submitted_at),
        'Eksik evrak istendi' => $date($a->missing_requested_at),
        'Onay / karar' => $date($a->reviewed_at),
        'Üyelik daveti' => $date($a->invitation_sent_at),
        'Üyelik tamamlandı' => $date($a->completed_at),
    ]);

    $extension = fn (TenantApplicationDocument $d) => strtolower(pathinfo($d->path, PATHINFO_EXTENSION));
    $images = $a->documents->filter(fn ($d) => str_starts_with($previewTypes[$extension($d)] ?? '', 'image/'))->values();
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Başvuru dosyası · {{ $a->name }} · {{ $portal ? $a->reference : '#'.$a->id }}</title>
    <style>
        :root { --ink: #1f2933; --soft: #52606d; --muted: #7b8794; --line: #e4e7eb; --bg: #f5f7fa; --brand: #45666D; --brand-soft: #eaf0f1; }
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 11px/1.4 "Helvetica Neue", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { max-width: 190mm; margin: 20px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { background: var(--brand); color: #fff; border: 0; border-radius: 8px; padding: 9px 16px; font: 600 12.5px/1 inherit; cursor: pointer; }
        .page { max-width: 190mm; margin: 12px auto 20px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 22px 24px; }

        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--brand); }
        .eyebrow { font-size: 9.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
        h1 { margin: 4px 0 2px; font-size: 20px; line-height: 1.2; color: var(--brand); }
        .sub { color: var(--soft); }
        .ref { text-align: right; white-space: nowrap; }
        .ref strong { display: block; font-size: 15px; letter-spacing: .03em; margin-top: 2px; }
        .pill { display: inline-block; margin-top: 6px; padding: 3px 10px; border-radius: 999px; font-weight: 700; font-size: 10.5px; }
        .tone-success { background: #e3f4ea; color: #1f7a4d; }
        .tone-warning { background: #fdf1df; color: #a35c00; }
        .tone-danger { background: #fde4e1; color: #b42318; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .card { border: 1px solid var(--line); border-radius: 9px; padding: 9px 12px 4px; break-inside: avoid; }
        .card.wide { grid-column: 1 / -1; }
        .card.accent { border-color: #c9d8db; background: #f8fbfb; }
        .card.accent .rows { display: grid; grid-template-columns: 1fr 1fr; column-gap: 18px; }
        .card.accent .row:nth-of-type(2) { border-top: 0; }
        h2 { margin: 0 0 3px; font-size: 9.5px; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
        .row { display: grid; grid-template-columns: 40% 1fr; gap: 8px; padding: 2.5px 0; border-top: 1px solid var(--line); }
        .row:first-of-type { border-top: 0; }
        .row span { color: var(--muted); }
        .row b { font-weight: 600; word-break: break-word; }
        .mono { font-family: "SFMono-Regular", Menlo, Consolas, monospace; font-size: 10.5px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border-top: 1px solid var(--line); vertical-align: top; }
        th { font-size: 9.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); border-top: 0; font-weight: 700; }
        .note { margin-top: 10px; padding: 8px 12px; border-left: 3px solid var(--brand); background: var(--bg); border-radius: 0 8px 8px 0; white-space: pre-line; break-inside: avoid; }
        .note.danger { border-color: #d64545; background: #fdf3f2; }
        .note b { display: block; font-size: 9.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 2px; }

        .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 26px; break-inside: avoid; }
        .sign { border-top: 1px solid var(--soft); padding-top: 5px; color: var(--muted); font-size: 10.5px; }
        footer { margin-top: 14px; padding-top: 8px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; color: var(--muted); font-size: 9.5px; }

        .attachment { break-before: page; }
        .attachment h2 { font-size: 11px; margin-bottom: 8px; }
        .attachment img { display: block; max-width: 100%; max-height: 245mm; margin: 0 auto; object-fit: contain; border: 1px solid var(--line); }

        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } header { flex-direction: column; } .ref { text-align: left; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; max-width: none; border: 0; border-radius: 0; padding: 0; }
            .page + .page { margin-top: 0; }
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
                <div class="eyebrow">İş ortağı başvuru dosyası · {{ $portal ? 'Bayi portalı' : 'Web formu' }}</div>
                <h1>{{ $a->name }}</h1>
                <div class="sub">{{ collect([$a->legal_name, $a->city])->filter()->implode(' · ') }}</div>
            </div>
            <div class="ref">
                <div class="eyebrow">{{ $portal ? 'Referans' : 'Başvuru no' }}</div>
                <strong>{{ $portal ? $a->reference : '#'.$a->id }}</strong>
                <span class="pill tone-{{ $statusTone }}">{{ $statusLabel }}</span>
            </div>
        </header>

        <div class="grid">
            @foreach ($cards as $title => $rows)
                <section @class(['card', 'wide accent' => $title === 'Açılan iş ortağı hesabı'])>
                    <h2>{{ $title }}</h2>
                    <div class="rows">
                        @foreach ($rows as $key => $value)
                            <div class="row"><span>{{ $key }}</span><b @class(['mono' => in_array($key, ['IBAN', 'VKN / TCKN', 'İş ortağı kodu'], true)])>{{ $value }}</b></div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if ($consentRows)
                <section class="card">
                    <h2>Onaylar</h2>
                    @foreach ($consentRows as $key => $value)
                        <div class="row"><span>{{ $key }}</span><b>{{ $value }}</b></div>
                    @endforeach
                </section>
            @endif

            <section class="card">
                <h2>Süreç</h2>
                @foreach ($timeline as $key => $value)
                    <div class="row"><span>{{ $key }}</span><b>{{ $value }}</b></div>
                @endforeach
                @if ($a->reviewer)
                    <div class="row"><span>İnceleyen</span><b>{{ $a->reviewer->name }}</b></div>
                @endif
                @if ($a->ip_address)
                    <div class="row"><span>Başvuru IP</span><b class="mono">{{ $a->ip_address }}</b></div>
                @endif
            </section>

            <section class="card wide">
                <h2>Yüklenen belgeler ({{ $a->documents->count() }})</h2>
                @if ($a->documents->isNotEmpty())
                    <table>
                        <tr><th>Belge</th><th>Dosya</th><th>Boyut</th><th>Yüklenme</th><th>Ek</th></tr>
                        @foreach ($a->documents->sortBy('created_at') as $document)
                            @php($attachment = $images->search(fn ($d) => $d->is($document)))
                            <tr>
                                <td>{{ TenantApplicationDocument::LABELS[$document->type] ?? $document->type }}</td>
                                <td>{{ $document->original_filename }}</td>
                                <td>{{ number_format(($document->size ?? 0) / 1048576, 1, ',', '.') }} MB</td>
                                <td>{{ $date($document->created_at) }}</td>
                                <td>{{ $attachment !== false ? 'Ek-'.($attachment + 1) : 'Ayrıca yazdırılır' }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <div class="row"><span>—</span><b>Belge yüklenmemiş.</b></div>
                @endif
            </section>
        </div>

        @if ($a->missing_note)
            <div class="note"><b>Eksik evrak notu</b>{{ $a->missing_note }}</div>
        @endif
        @if ($a->status === TenantApplication::STATUS_REJECTED && $a->rejection_reason)
            <div class="note danger"><b>Red gerekçesi</b>{{ $a->rejection_reason }}</div>
        @endif
        @if ($a->message)
            <div class="note"><b>Başvuru notu</b>{{ $a->message }}</div>
        @endif

        <div class="signs">
            <div class="sign">İnceleyen / onaylayan · Ad Soyad, İmza</div>
            <div class="sign">Tarih</div>
        </div>

        <footer>
            <span>{{ config('app.name') }} · Gizli — iç kullanım</span>
            <span>Çıktı: {{ $date(now()) }} · {{ auth()->user()?->name }}</span>
        </footer>
    </main>

    @foreach ($images as $index => $document)
        <section class="page attachment">
            <h2>Ek-{{ $index + 1 }} · {{ TenantApplicationDocument::LABELS[$document->type] ?? $document->type }} — {{ $document->original_filename }}</h2>
            <img src="{{ route('tenant-applications.documents.preview', [$a->id, $document->id]) }}" alt="{{ $document->original_filename }}">
        </section>
    @endforeach

    <script>
        // Ek görseller yüklenince yazdırma penceresi açılır.
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>
