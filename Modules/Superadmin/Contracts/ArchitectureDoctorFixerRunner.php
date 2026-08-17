<?php

namespace Modules\Superadmin\Contracts;

/**
 * Mimari Doktor'un FAILED kurallarını triage edip düzelten ajanı çalıştırır.
 * Gerçek sürücü (bkz. ClaudeCliFixerRunner) headless Claude Code CLI'ı bir
 * subprocess olarak tetikler; Mock sürücü hiçbir şeye dokunmadan sabit bir
 * "değişiklik yok" sonucu döner (bkz. MockFixerRunner).
 */
interface ArchitectureDoctorFixerRunner
{
    /**
     * @return array{exitCode: int, output: string}
     */
    public function run(string $workdir, string $prompt): array;
}
