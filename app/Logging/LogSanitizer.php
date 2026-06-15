<?php

namespace App\Logging;

use Illuminate\Support\Str;

/**
 * KVKK PII maskeleme sanitizer'ı.
 *
 * Kullanım:
 *   $clean = (new LogSanitizer())->sanitize($requestData);
 *   // veya
 *   $clean = app(LogSanitizer::class)->sanitize($requestData);
 *
 * Kurallar:
 *  - DENY  → anahtar tamamen çıkarılır (parola, token, secret, cvv, vb.)
 *  - MASK  → değer maskelenir (e-posta, telefon, TC, IBAN, vergi no, IP)
 *  - Diğer → değişmeden bırakılır (dizi ise özyinelemeli işlenir)
 */
class LogSanitizer
{
    /**
     * Anahtarın kendisi bu sözcüklerden birini içeriyorsa DENY (tamamen sil).
     * Büyük/küçük harf duyarsız substring eşleşmesi.
     */
    private const DENY_SUBSTRINGS = ['password', 'token', 'secret', 'cvv', 'card_number'];

    /**
     * Tam anahtar adı eşleşmesiyle DENY edilecekler (küçük harfe normalize edilerek karşılaştırılır).
     */
    private const DENY_EXACT = [
        'api_key', 'apikey',
        'cvc',
        'cardnumber',
        'pin', 'pin_code', 'user_pin',
    ];

    /**
     * IP maskeleme için eşleştirilecek tam anahtar adları (küçük harf normalize).
     */
    private const IP_EXACT = ['ip', 'ip_address'];

    /**
     * Diziyi özyinelemeli tarayarak KVKK/güvenlik kurallarını uygular.
     */
    public function sanitize(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // ── DENY: tamamen çıkar ──────────────────────────────────────────
            if ($this->isDenied($lowerKey)) {
                continue;
            }

            // ── İç içe dizi → özyinele ───────────────────────────────────────
            if (is_array($value)) {
                $result[$key] = $this->sanitize($value);
                continue;
            }

            // ── MASK: sadece dolu string değerlerde uygulanır ────────────────
            if (is_string($value) && $value !== '') {
                $masked = $this->tryMask($lowerKey, $value);
                $result[$key] = $masked;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Public maskeleme yardımcıları (testlerden ve dış koddan çağrılabilir)
    // -------------------------------------------------------------------------

    /**
     * E-posta maskeleme.
     * ahmet@gmail.com → a***@g***.com
     * (Yerel kısmın 1. karakteri + domain adının 1. karakteri görünür, TLD korunur.)
     */
    public function maskEmail(string $email): string
    {
        if ($email === '') {
            return $email;
        }

        $atPos = strpos($email, '@');

        if ($atPos === false) {
            // @ yoksa orta kısmı maskele
            $len = strlen($email);
            if ($len <= 2) {
                return str_repeat('*', $len);
            }
            return $email[0] . str_repeat('*', $len - 2) . $email[$len - 1];
        }

        $local  = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1); // gmail.com

        // Yerel kısım: ilk karakter görünür, geri kalanı maskele
        $maskedLocal = $this->maskKeepStart($local, 1);

        // Domain: TLD'yi bul (son nokta)
        $dotPos = strrpos($domain, '.');
        if ($dotPos === false) {
            // TLD yok — tamamen maskele
            $maskedDomain = $this->maskKeepStart($domain, 1);
        } else {
            $domainName = substr($domain, 0, $dotPos); // gmail
            $tld        = substr($domain, $dotPos);    // .com
            $maskedDomain = $this->maskKeepStart($domainName, 1) . $tld;
        }

        return $maskedLocal . '@' . $maskedDomain;
    }

    /**
     * Telefon maskeleme.
     * İlk 4 ve son 2 karakter görünür, geri kalanı '*'.
     * Örnek: 05321234567 → 0532*****67
     *
     * Not: app/Models/User.php::getMaskedPhoneAttribute() ilk 4 + son 3 gösterir.
     * Bu sınıf plan spesifikasyonunu (ilk 4 + son 2) takip eder ve
     * LogSanitizer tek kaynak olarak User::getMaskedPhoneAttribute()'ın
     * yerini alacaktır.
     */
    public function maskPhone(string $phone): string
    {
        if ($phone === '') {
            return $phone;
        }

        $len = strlen($phone);

        if ($len <= 6) {
            // Çok kısa: tamamını maskele
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 4)
            . str_repeat('*', $len - 6)
            . substr($phone, -2);
    }

    /**
     * TC Kimlik No maskeleme.
     * İlk 3 + son 2 görünür, geri kalanı '*'.
     * Örnek: 12345678901 → 123******01
     */
    public function maskNationalId(string $v): string
    {
        if ($v === '') {
            return $v;
        }

        $len = strlen($v);

        if ($len <= 5) {
            return str_repeat('*', $len);
        }

        return substr($v, 0, 3)
            . str_repeat('*', $len - 5)
            . substr($v, -2);
    }

    /**
     * IBAN maskeleme.
     * İlk 6 + son 4 görünür, geri kalanı '*'.
     * Örnek: TR330006100519786457841326 → TR3300****************1326
     */
    public function maskIban(string $v): string
    {
        if ($v === '') {
            return $v;
        }

        $len = strlen($v);

        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        return substr($v, 0, 6)
            . str_repeat('*', $len - 10)
            . substr($v, -4);
    }

    /**
     * Vergi no maskeleme.
     * Son 3 karakter görünür, geri kalanı '*'.
     * Örnek: 1234567890 → *******890
     */
    public function maskTaxNo(string $v): string
    {
        if ($v === '') {
            return $v;
        }

        $len = strlen($v);

        if ($len <= 3) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 3) . substr($v, -3);
    }

    /**
     * IP maskeleme.
     * IPv4: ilk iki oktet görünür, son iki oktet → 'x'.
     * Örnek: 88.230.45.12 → 88.230.x.x
     * Diğer (IPv6 vb.): orta kısmı maskele.
     */
    public function maskIp(string $ip): string
    {
        if ($ip === '') {
            return $ip;
        }

        // IPv4 kontrolü
        $parts = explode('.', $ip);
        if (count($parts) === 4 && array_reduce($parts, fn ($carry, $p) => $carry && is_numeric($p), true)) {
            return $parts[0] . '.' . $parts[1] . '.x.x';
        }

        // IPv4 değil → orta kısmı maskele
        $len = strlen($ip);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        return $ip[0] . str_repeat('*', $len - 2) . $ip[$len - 1];
    }

    // -------------------------------------------------------------------------
    // Özel yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Anahtarın DENY olup olmadığını belirler (küçük harf normalize edilmiş key gelir).
     */
    private function isDenied(string $lowerKey): bool
    {
        // Substring tabanlı DENY
        foreach (self::DENY_SUBSTRINGS as $sub) {
            if (str_contains($lowerKey, $sub)) {
                return true;
            }
        }

        // Tam eşleşme DENY
        if (in_array($lowerKey, self::DENY_EXACT, true)) {
            return true;
        }

        return false;
    }

    /**
     * Anahtara göre uygun maskeleme metodunu çalıştırır.
     * Eşleşme yoksa değeri olduğu gibi döner.
     */
    private function tryMask(string $lowerKey, string $value): string
    {
        // E-posta
        if (str_contains($lowerKey, 'email')) {
            return $this->maskEmail($value);
        }

        // Telefon
        if (str_contains($lowerKey, 'phone')
            || $lowerKey === 'telefon'
            || $lowerKey === 'gsm'
        ) {
            return $this->maskPhone($value);
        }

        // TC Kimlik / Ulusal kimlik
        if (in_array($lowerKey, ['tc', 'tckn', 'national_id', 'tc_no', 'identity_no'], true)) {
            return $this->maskNationalId($value);
        }

        // IBAN
        if (str_contains($lowerKey, 'iban')) {
            return $this->maskIban($value);
        }

        // Vergi numarası
        if (in_array($lowerKey, ['tax_no', 'vergi_no'], true)) {
            return $this->maskTaxNo($value);
        }

        // IP adresi (tam eşleşme: 'ip' veya 'ip_address')
        if (in_array($lowerKey, self::IP_EXACT, true)) {
            return $this->maskIp($value);
        }

        // Eşleşme yok → olduğu gibi bırak
        return $value;
    }

    /**
     * Dizenin başından `$keep` karakter görünür bırakır, geri kalanını '*' ile maskeler.
     */
    private function maskKeepStart(string $str, int $keep): string
    {
        $len = strlen($str);
        if ($len <= $keep) {
            return $str;
        }
        return substr($str, 0, $keep) . str_repeat('*', $len - $keep);
    }
}
