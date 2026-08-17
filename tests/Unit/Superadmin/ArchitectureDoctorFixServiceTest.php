<?php

namespace Tests\Unit\Superadmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Superadmin\Contracts\ArchitectureDoctorFixerRunner;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;
use Modules\Superadmin\Services\ArchitectureDoctorFixer\MockFixerRunner;
use Modules\Superadmin\Services\ArchitectureDoctorFixService;
use Tests\TestCase;

/**
 * ArchitectureDoctorFixService'in worktree add/remove + commit-veya-branch-sil
 * akışını, gerçek proje reposuna hiç dokunmadan geçici bir fixture git deposu
 * üzerinde doğrular (bkz. plan: "gerçek proje reposuna dokunmadan ... worktree
 * add/remove akışını MockFixerRunner ile doğrula"). `repo_path`/`worktree_base_path`
 * bu fixture'a override edilir; gerçek `claude` CLI veya headless subprocess
 * hiçbir zaman çalışmaz (agent, ArchitectureDoctorFixerRunner ile test double'lanır).
 */
class ArchitectureDoctorFixServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $repoPath;

    private string $worktreeBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir().'/adf-fixture-'.uniqid();
        $this->repoPath = "{$this->fixtureRoot}/repo";
        $this->worktreeBase = "{$this->fixtureRoot}/worktrees";

        $this->initFixtureRepo();

        config([
            'superadmin.architecture_doctor.repo_path' => $this->repoPath,
            'superadmin.architecture_doctor.worktree_base_path' => $this->worktreeBase,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    /**
     * Fixture'daki untracked `.claude/agents/` (servis tarafından symlink'lenir)
     * gerçek bir kod değişikliği olmadığı halde commit'e sızmamalı — canlı repoda
     * yaşanan regresyonun testi (bkz. EXCLUDE_AGENTS_PATHSPEC).
     */
    public function test_no_changes_run_removes_worktree_and_deletes_the_branch(): void
    {
        $service = new ArchitectureDoctorFixService(new MockFixerRunner);
        $fixRun = ArchitectureDoctorFixRun::create(['status' => ArchitectureDoctorFixRun::STATUS_QUEUED]);

        $service->run($fixRun);

        $fixRun->refresh();

        $this->assertSame(ArchitectureDoctorFixRun::STATUS_SUCCESS, $fixRun->status);
        $this->assertSame([], $fixRun->files_changed);
        $this->assertNotNull($fixRun->branch_name);
        $this->assertDirectoryDoesNotExist($this->worktreeDirFor($fixRun));
        $this->assertBranchDoesNotExist($fixRun->branch_name);
    }

    public function test_changes_are_committed_to_a_new_branch_without_touching_the_live_checkout(): void
    {
        $runner = new class implements ArchitectureDoctorFixerRunner
        {
            public function run(string $workdir, string $prompt): array
            {
                file_put_contents("{$workdir}/fixed-file.txt", "düzeltildi\n");

                return ['exitCode' => 0, 'output' => 'agent bir dosya değiştirdi'];
            }
        };

        $service = new ArchitectureDoctorFixService($runner);
        $fixRun = ArchitectureDoctorFixRun::create(['status' => ArchitectureDoctorFixRun::STATUS_QUEUED]);

        $service->run($fixRun);

        $fixRun->refresh();

        $this->assertSame(ArchitectureDoctorFixRun::STATUS_SUCCESS, $fixRun->status);
        $this->assertSame(['fixed-file.txt'], $fixRun->files_changed);
        $this->assertDirectoryDoesNotExist($this->worktreeDirFor($fixRun));

        // Branch hâlâ mevcut (silinmedi) ve commit'i içeriyor.
        $lsTree = Process::path($this->repoPath)->run(['git', 'ls-tree', '-r', '--name-only', $fixRun->branch_name]);
        $this->assertStringContainsString('fixed-file.txt', $lsTree->output());

        // Canlı checkout'un HEAD'i hiç değişmedi, worktree'nin dosyası sızmadı.
        $this->assertFileDoesNotExist("{$this->repoPath}/fixed-file.txt");
        $headSha = trim(Process::path($this->repoPath)->run(['git', 'rev-parse', 'HEAD'])->output());
        $this->assertSame($fixRun->base_commit_sha, $headSha);
    }

    private function worktreeDirFor(ArchitectureDoctorFixRun $fixRun): string
    {
        // Servis dizin adını "<id>-<Ymdhis>" şeklinde üretiyor; benzersiz olduğu
        // için glob ile arıyoruz.
        $matches = glob("{$this->worktreeBase}/{$fixRun->id}-*");

        return $matches[0] ?? "{$this->worktreeBase}/{$fixRun->id}-missing";
    }

    private function assertBranchDoesNotExist(string $branch): void
    {
        $result = Process::path($this->repoPath)->run(['git', 'branch', '--list', $branch]);
        $this->assertSame('', trim($result->output()));
    }

    /**
     * Gerçek repoyu taklit eder: `storage/app/.gitignore` TRACKED bir iskelet
     * marker'ıdır (bkz. gerçek `storage/app/.gitignore`: `*` + `!.gitignore`) —
     * bu yüzden ArchitectureDoctorFixService storage/'ı symlink'lemez, worktree
     * checkout'u onu zaten içerir. `.env`/`vendor` ise gerçekten tracked
     * DEĞİLDİR (gitignored, trailing-slash'sız desen), bu yüzden servis onları
     * symlink'lemek zorundadır — fixture bunu da taklit eder. `artisan` sahte
     * bir PHP betiği: `architecture:doctor --json` çağrısını taklit edip
     * storage/app altına minimal geçerli bir rapor yazar (gerçek Laravel
     * kurulumu gerektirmez).
     */
    private function initFixtureRepo(): void
    {
        File::ensureDirectoryExists($this->repoPath);
        File::ensureDirectoryExists($this->worktreeBase);

        Process::path($this->repoPath)->run(['git', 'init', '-q']);
        Process::path($this->repoPath)->run(['git', 'config', 'user.email', 'fixer-test@example.com']);
        Process::path($this->repoPath)->run(['git', 'config', 'user.name', 'Fixer Test']);

        File::put("{$this->repoPath}/.gitignore", "/vendor\n/node_modules\n.env\n");

        $fakeArtisan = <<<'PHP'
        <?php
        $dir = __DIR__.'/storage/app';
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/architecture-doctor.json', json_encode([
            'generated_at' => date('c'),
            'results' => [],
            'category_summaries' => [],
        ]));
        echo "architecture-doctor fake ok\n";
        PHP;
        File::put("{$this->repoPath}/artisan", $fakeArtisan);

        // storage/app/.gitignore: gerçek repodaki gibi TRACKED bir marker —
        // dizinin kendisi worktree checkout'unda hazır bulunur, içeriği (üretilen
        // architecture-doctor.json dahil) git'e hiç görünmez.
        File::ensureDirectoryExists("{$this->repoPath}/storage/app");
        File::put("{$this->repoPath}/storage/app/.gitignore", "*\n!.gitignore\n");

        // Gitignore'lu (ve tracked OLMAYAN) yollar: gerçek repoda symlink'lenmesi
        // gereken şeyleri temsil eder.
        File::ensureDirectoryExists("{$this->repoPath}/vendor");
        File::put("{$this->repoPath}/.env", "APP_ENV=testing\n");

        // Gerçek repodaki mevcut durumu taklit eder: .claude/agents henüz
        // commit'lenmemiş ama gitignore'lu DA değil (sade untracked). Servis
        // bunu symlink'lediği için EXCLUDE_AGENTS_PATHSPEC olmasa git status/add
        // bunu "yeni dosya" sanıp hiçbir gerçek değişiklik yokken bile commit'e
        // sızdırırdı (canlı repoda gerçekten yaşanan regresyon — bkz. servisteki not).
        File::ensureDirectoryExists("{$this->repoPath}/.claude/agents");
        File::put("{$this->repoPath}/.claude/agents/fake-agent.md", "# fake agent\n");

        Process::path($this->repoPath)->run(['git', 'add', '.gitignore', 'artisan', 'storage/app/.gitignore']);
        Process::path($this->repoPath)->run(['git', 'commit', '-q', '-m', 'init']);
    }
}
