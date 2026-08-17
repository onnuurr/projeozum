<?php

namespace Modules\Superadmin\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Models\FailedJob;

/**
 * `failed_jobs` tablosunu superadmin panelinde listelemek ve panelden toplu
 * queue:retry / queue:flush tetiklemek için ince bir sarmalayıcı.
 */
class FailedJobService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $limit = 100): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return FailedJob::query()
            ->latest('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (FailedJob $job) => [
                'id'         => $job->id,
                'uuid'       => $job->uuid,
                'connection' => $job->connection,
                'queue'      => $job->queue,
                'jobName'    => $this->jobDisplayName($job->payload),
                'exception'  => $this->exceptionSummary($job->exception),
                'failedAt'   => $job->failed_at?->toIso8601String(),
            ])
            ->all();
    }

    public function count(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return FailedJob::query()->count();
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
    }

    public function flush(): void
    {
        Artisan::call('queue:flush');
    }

    private function jobDisplayName(?string $payload): string
    {
        $decoded = json_decode((string) $payload, true);

        return $decoded['displayName'] ?? $decoded['job'] ?? '—';
    }

    /** Exception stack trace'inin ilk satırı — mesajı özetler, tam trace panelde şişirmez. */
    private function exceptionSummary(?string $exception): string
    {
        $firstLine = strtok((string) $exception, "\n");

        return $firstLine !== false ? $firstLine : '—';
    }
}
