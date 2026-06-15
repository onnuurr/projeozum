<?php

namespace App\Logging;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Uygulama aktivite kayıtçısı.
 *
 * Kullanım:
 *   app(ActivityLogger::class)->log('user.login', 'Giriş yapıldı', [
 *       'module'     => 'auth',
 *       'causer'     => $user,
 *       'subject'    => $resource,
 *       'properties' => ['key' => 'val'],
 *       'level'      => 'info',
 *   ]);
 */
class ActivityLogger
{
    public function __construct(
        private readonly LogSanitizer $sanitizer,
    ) {}

    /**
     * Bir aktivite kaydı oluşturur ve döner.
     */
    public function log(string $action, string $description, array $opts = []): ActivityLog
    {
        // ── Causer ────────────────────────────────────────────────────────────
        $causer   = $opts['causer'] ?? auth()->user();
        $causerId = $causer?->id ?? null;

        // Causer etiketi: isim varsa "İsim (masked@email.com)", sadece e-posta varsa maskele
        $causerLabel = null;
        if ($causer !== null) {
            $maskedEmail = isset($causer->email) && $causer->email !== ''
                ? $this->sanitizer->maskEmail($causer->email)
                : null;

            if (isset($causer->name) && $causer->name !== '') {
                $causerLabel = $maskedEmail !== null
                    ? "{$causer->name} ({$maskedEmail})"
                    : $causer->name;
            } else {
                $causerLabel = $maskedEmail;
            }
        }

        // ── Subject ───────────────────────────────────────────────────────────
        $subject     = $opts['subject'] ?? null;
        $subjectType = null;
        $subjectId   = null;

        if ($subject instanceof Model) {
            $subjectType = get_class($subject);
            $subjectId   = $subject->getKey();
        }

        // ── Module çıkarımı ───────────────────────────────────────────────────
        $module = $opts['module'] ?? $this->inferModule($subject);

        // ── Properties sanitizasyonu ──────────────────────────────────────────
        $properties = $this->sanitizer->sanitize($opts['properties'] ?? []);

        // ── IP / User-Agent (console'da request()->ip() null dönebilir) ───────
        $ipAddress = null;
        $userAgent = null;

        try {
            if (app()->runningInConsole() === false || request()->hasSession()) {
                $ipAddress = request()->ip();
                $userAgent = request()->userAgent();
            }
        } catch (\Throwable) {
            // Console ya da test bağlamında request() yoksa sessizce devam et
        }

        // ── Kayıt oluştur ─────────────────────────────────────────────────────
        return ActivityLog::create([
            'module'       => $module,
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'causer_id'    => $causerId,
            'causer_label' => $causerLabel,
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
            'properties'   => $properties,
            'level'        => $opts['level'] ?? 'info',
        ]);
    }

    // -------------------------------------------------------------------------
    // Özel yardımcılar
    // -------------------------------------------------------------------------

    /**
     * Model sınıf adından modül adını çıkarır.
     * Modules\Product\Models\Product → 'product'
     */
    private function inferModule(mixed $subject): ?string
    {
        if (!($subject instanceof Model)) {
            return null;
        }

        $class = get_class($subject);

        // Modules\<Name>\... kalıbı
        if (preg_match('/^Modules\\\\([^\\\\]+)\\\\/i', $class, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
