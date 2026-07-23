<?php

namespace ArchitectureDoctor\Console;

use ArchitectureDoctor\Engine\AnalysisReport;
use ArchitectureDoctor\Engine\RuleRunner;
use ArchitectureDoctor\Policies\ExecutionContext;
use ArchitectureDoctor\Policies\Policy;
use ArchitectureDoctor\Report\ConsoleFormatter;
use ArchitectureDoctor\Report\JsonFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * schema:audit ile aynı ton: salt-okuma, hiçbir şey silmez/değiştirmez, sadece rapor üretir.
 */
class ArchitectureDoctorCommand extends Command
{
    protected $signature = 'architecture:doctor
        {--ci : CI modunda çalıştır; kritik (critical) ihlal varsa exit code 1 döner}
        {--json : JSON çıktısı üret ve storage/app/architecture-doctor.json dosyasına yaz}';

    protected $description = "Kayıtlı Architecture Rule'lara göre proje mimarisini denetler (salt-okuma)";

    public function handle(RuleRunner $runner, Policy $policy): int
    {
        $report = new AnalysisReport($runner->run());
        $context = $this->option('ci') ? ExecutionContext::Ci : ExecutionContext::Local;

        if ($this->option('json')) {
            $json = (new JsonFormatter)->toJson($report);

            File::ensureDirectoryExists(storage_path('app'));
            File::put(storage_path('app/architecture-doctor.json'), $json);

            $this->line($json);
        } else {
            $this->line((new ConsoleFormatter)->format($report));
        }

        return $policy->exitCode($report, $context);
    }
}
