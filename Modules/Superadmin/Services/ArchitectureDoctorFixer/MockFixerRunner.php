<?php

namespace Modules\Superadmin\Services\ArchitectureDoctorFixer;

use Modules\Superadmin\Contracts\ArchitectureDoctorFixerRunner;

/**
 * Deterministik sürücü — dosya sistemine dokunmaz, sabit bir "değişiklik yok"
 * sonucu döner. `architecture_doctor.enabled=false` iken (varsayılan) ve test
 * ortamında bind edilir (bkz. SuperadminServiceProvider'daki config-switch,
 * Modules/Creative'deki Gemini/Mock desenin birebir aynısı).
 */
class MockFixerRunner implements ArchitectureDoctorFixerRunner
{
    public function run(string $workdir, string $prompt): array
    {
        return [
            'exitCode' => 0,
            'output' => '[mock] architecture-doctor-fixer çalıştırılmadı — architecture_doctor.enabled=false.',
        ];
    }
}
