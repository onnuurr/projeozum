<?php

namespace Modules\Superadmin\Services\ArchitectureDoctorFixer;

use Illuminate\Support\Facades\Process;
use Modules\Superadmin\Contracts\ArchitectureDoctorFixerRunner;

/**
 * Sunucuda kurulu `claude` CLI'ı headless (`-p`) modda, izole bir git worktree
 * içinde subprocess olarak çalıştırır (bkz. ArchitectureDoctorFixService).
 *
 * `--dangerously-skip-permissions` gereklidir çünkü headless modda interaktif
 * onay istenemez; güvenlik sınırı olarak agent tanımının kendi `tools:`
 * allowlist'i (Bash, Read, Edit, Grep, Glob — Write/Agent/git-push yok) ve
 * izole worktree + branch (asıl checkout hiç etkilenmez, push/merge yapılmaz)
 * kullanılıyor. Bu bilinçli bir trade-off.
 */
class ClaudeCliFixerRunner implements ArchitectureDoctorFixerRunner
{
    public function run(string $workdir, string $prompt): array
    {
        $result = Process::path($workdir)
            ->timeout((int) config('superadmin.architecture_doctor.fix_timeout', 3600))
            ->run([
                config('superadmin.architecture_doctor.claude_binary', 'claude'),
                '-p', $prompt,
                '--dangerously-skip-permissions',
            ]);

        return [
            'exitCode' => $result->exitCode(),
            'output' => $result->output().$result->errorOutput(),
        ];
    }
}
