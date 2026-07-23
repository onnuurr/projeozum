<?php

namespace Tests\Unit\ArchitectureDoctor\Migrations;

use ArchitectureDoctor\Rules\Migrations\MigrationNotEditedAfterMergeRule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Bu kural gerçek git geçmişine bakar, bu yüzden fixture olarak izole, geçici bir git
 * repo'su kuruyoruz — proje repo'sunun kendi commit geçmişine bağımlı olmadan
 * deterministik test edilebilir.
 */
class MigrationNotEditedAfterMergeRuleTest extends TestCase
{
    private string $repoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoPath = sys_get_temp_dir().'/architecture-doctor-git-test-'.uniqid();
        mkdir($this->repoPath.'/database/migrations', recursive: true);

        $this->git(['init', '--initial-branch=main']);
        $this->git(['config', 'user.email', 'test@example.com']);
        $this->git(['config', 'user.name', 'Test']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->remove($this->repoPath);

        parent::tearDown();
    }

    public function test_it_does_not_flag_a_migration_committed_only_once(): void
    {
        $file = $this->repoPath.'/database/migrations/2026_07_25_000000_single_commit.php';
        file_put_contents($file, "<?php\n// v1\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'add migration']);

        $rule = new MigrationNotEditedAfterMergeRule([$this->repoPath.'/database/migrations'], $this->repoPath);

        $this->assertSame([], $rule->check());
    }

    public function test_it_flags_a_migration_committed_again_after_being_added(): void
    {
        $file = $this->repoPath.'/database/migrations/2026_07_25_000001_edited_twice.php';
        file_put_contents($file, "<?php\n// v1\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'add migration']);

        file_put_contents($file, "<?php\n// v2 - edited after merge\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'edit migration after it already shipped']);

        $rule = new MigrationNotEditedAfterMergeRule([$this->repoPath.'/database/migrations'], $this->repoPath);

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('edited_twice.php', $findings[0]->file);
    }

    public function test_it_ignores_migrations_dated_on_or_before_introduced_in(): void
    {
        $file = $this->repoPath.'/database/migrations/2026_07_23_000000_edited_twice_but_old.php';
        file_put_contents($file, "<?php\n// v1\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'add migration']);

        file_put_contents($file, "<?php\n// v2\n");
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'edit']);

        $rule = new MigrationNotEditedAfterMergeRule([$this->repoPath.'/database/migrations'], $this->repoPath);

        $this->assertSame([], $rule->check());
    }

    private function git(array $args): void
    {
        $process = new Process(['git', ...$args], $this->repoPath);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail('git command failed: '.$process->getErrorOutput());
        }
    }
}
