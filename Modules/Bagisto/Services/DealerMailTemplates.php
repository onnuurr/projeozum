<?php

namespace Modules\Bagisto\Services;

use App\Contracts\PlatformSettings;
use Illuminate\Mail\Mailable;

/**
 * Bayi e-postası şablon grubu (Başvuru, İade). Tasarım her grubun blade görünümünde sabittir; her
 * adımın açık/kapalı durumu, konusu, giriş ve alt paragrafı Bayi E-postaları sayfasından düzenlenir
 * ve platform ayarlarında (App\Contracts\PlatformSettings) grubun settingsGroup()'unda saklanır.
 * Kaydı olmayan adım catalog() varsayılanlarını kullanır.
 *
 * Metinlerde {{user.name}} gibi etiketler kullanılabilir; gönderim anında değerlerle doldurulur
 * (bkz. tags(), alt sınıfların variables()'ı). Bilinmeyen etiket kayıtta reddedilir.
 */
abstract class DealerMailTemplates
{
    private const TAG_PATTERN = '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/';

    public function __construct(protected PlatformSettings $settings) {}

    /** Sayfadaki grup anahtarı (istek parametresi). */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Grubun metinlerini düzenleme / önizleme / test gönderimi yetkisi. */
    abstract public function permission(): string;

    abstract protected function settingsGroup(): string;

    /**
     * Adımlar gönderim sırasıyla.
     *
     * @return array<string, array{label: string, description: string, defaults: array{subject: string, intro: string, body: string}}>
     */
    abstract public function catalog(): array;

    /**
     * Adımda kullanılabilen etiketler (etiket => açıklama).
     *
     * @return array<string, string>
     */
    abstract public function tags(string $stage): array;

    /**
     * Kaydedilmemiş taslağın örnek veriyle işlenmiş e-postası (önizleme ve test gönderimi).
     *
     * @param  array{subject: string, intro: string, body?: string|null}  $template
     */
    abstract public function previewMail(string $stage, array $template, string $email): Mailable;

    /**
     * Kayıtlı metinler, kaydı olmayan alanlar varsayılanla doldurulmuş.
     *
     * @return array<string, array{enabled: bool, subject: string, intro: string, body: string}>
     */
    public function templates(): array
    {
        $stored = $this->settings->group($this->settingsGroup());

        return collect($this->catalog())
            ->map(function (array $stage, string $key) use ($stored) {
                $saved = is_array($stored[$key] ?? null) ? $stored[$key] : [];

                return [
                    'enabled' => (bool) ($saved['enabled'] ?? true),
                    'subject' => (string) ($saved['subject'] ?? $stage['defaults']['subject']),
                    'intro' => (string) ($saved['intro'] ?? $stage['defaults']['intro']),
                    'body' => (string) ($saved['body'] ?? $stage['defaults']['body']),
                ];
            })
            ->all();
    }

    /** @return array{enabled: bool, subject: string, intro: string, body: string} */
    public function template(string $stage): array
    {
        return $this->templates()[$stage];
    }

    /**
     * @param  array<string, array{enabled: bool, subject: string, intro: string, body?: string|null}>  $templates  yalnızca katalogdaki adımlar dikkate alınır
     */
    public function update(array $templates): void
    {
        $values = collect($templates)
            ->only(array_keys($this->catalog()))
            ->map(fn (array $template) => [
                'enabled' => (bool) $template['enabled'],
                'subject' => trim((string) $template['subject']),
                'intro' => trim((string) $template['intro']),
                'body' => trim((string) ($template['body'] ?? '')),
            ])
            ->all();

        $this->settings->putGroup($this->settingsGroup(), $values);
    }

    /**
     * Metinde adımın tanımadığı etiketler.
     *
     * @return array<int, string>
     */
    public function unknownTags(string $stage, ?string $text): array
    {
        preg_match_all(self::TAG_PATTERN, (string) $text, $matches);

        return array_values(array_unique(array_diff($matches[1], array_keys($this->tags($stage)))));
    }

    /**
     * Etiketleri değerleriyle değiştirir (düz metin döner; HTML kaçışı görünümde yapılır).
     *
     * @param  array<string, string>  $vars
     */
    public function render(?string $text, array $vars): string
    {
        return preg_replace_callback(
            self::TAG_PATTERN,
            fn (array $m) => array_key_exists($m[1], $vars) ? $vars[$m[1]] : $m[0],
            (string) $text,
        );
    }

    /**
     * Adımın etiketleri doldurulmuş metinleri; $template verilmezse kayıtlı metin kullanılır.
     *
     * @param  array<string, string>  $vars
     * @param  array{subject: string, intro: string, body?: string|null}|null  $template
     * @return array{subject: string, intro: string, body: string}
     */
    public function texts(string $stage, array $vars, ?array $template = null): array
    {
        $template ??= $this->template($stage);

        return [
            'subject' => $this->render($template['subject'], $vars),
            'intro' => $this->render($template['intro'], $vars),
            'body' => $this->render($template['body'] ?? '', $vars),
        ];
    }
}
