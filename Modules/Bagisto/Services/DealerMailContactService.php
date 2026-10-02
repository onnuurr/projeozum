<?php

namespace Modules\Bagisto\Services;

use App\Contracts\PlatformSettings;

/**
 * Bayi e-postalarının (başvuru, iade) altındaki destek / danışman kartı. Bayi E-postaları
 * sayfasının "İletişim bilgileri" sekmesinden düzenlenir; platform ayarlarında 'dealer_mail'
 * grubunun 'contact' anahtarında saklanır. Kaydedilmemiş (boş) alan .env'deki
 * bagisto.invitation_mail değerine düşer.
 */
class DealerMailContactService
{
    public const GROUP = 'dealer_mail';

    public const KEY = 'contact';

    public const FIELDS = ['support_email', 'advisor_name', 'advisor_title', 'advisor_phone', 'advisor_whatsapp'];

    public function __construct(private PlatformSettings $settings) {}

    /** @return array{support_email: ?string, advisor_name: ?string, advisor_title: ?string, advisor_phone: ?string, advisor_whatsapp: ?string} */
    public function contact(): array
    {
        $stored = $this->stored();
        $fallback = $this->fallback();

        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => filled($stored[$field]) ? $stored[$field] : $fallback[$field]])
            ->all();
    }

    /** @return array<string, ?string> Alan kaydedilmemişse kullanılan .env (bagisto.invitation_mail) değerleri. */
    public function fallback(): array
    {
        return [
            'support_email' => config('bagisto.invitation_mail.support_email') ?: config('mail.from.address'),
            'advisor_name' => config('bagisto.invitation_mail.advisor.name'),
            'advisor_title' => config('bagisto.invitation_mail.advisor.title'),
            'advisor_phone' => config('bagisto.invitation_mail.advisor.phone'),
            'advisor_whatsapp' => config('bagisto.invitation_mail.advisor.whatsapp'),
        ];
    }

    /** @return array<string, mixed> Kayıtlı ham alanlar (sayfa formu için; boşsa .env değeri kullanılır). */
    public function stored(): array
    {
        $stored = $this->settings->group(self::GROUP)[self::KEY] ?? [];

        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => is_array($stored) ? ($stored[$field] ?? null) : null])
            ->all();
    }

    /** @param  array<string, string|null>  $contact */
    public function update(array $contact): void
    {
        $this->settings->putGroup(self::GROUP, [self::KEY => collect($contact)->only(self::FIELDS)->all()]);
    }
}
