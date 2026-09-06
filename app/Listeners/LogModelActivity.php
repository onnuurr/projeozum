<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Tüm Eloquent modellerinde create/update/delete işlemlerini otomatik loglar.
 *
 * `AppServiceProvider::boot()`'ta joker (wildcard) Eloquent event'lerine
 * (`eloquent.created: *` vb.) bağlanır — tek tek her modele observer eklemeye
 * gerek kalmadan TÜM modelleri kapsar. Sadece Eloquent üzerinden yapılan
 * işlemleri yakalar; ham SQL/`DB::table()->truncate()`/psql bunu atlar
 * (bkz. Postgres `log_statement='mod'` — bu ikisi birbirini tamamlar).
 */
class LogModelActivity
{
    private const EXCLUDED = [
        ActivityLog::class,
        ErrorLog::class,
    ];

    private const VERB_LABELS = [
        'created' => 'oluşturuldu',
        'updated' => 'güncellendi',
        'deleted' => 'silindi',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(string $event, array $data): void
    {
        $this->record($data[0], 'created', 'info');
    }

    public function updated(string $event, array $data): void
    {
        $this->record($data[0], 'updated', 'info');
    }

    public function deleted(string $event, array $data): void
    {
        $this->record($data[0], 'deleted', 'warning');
    }

    private function record(Model $model, string $verb, string $level): void
    {
        if (in_array(get_class($model), self::EXCLUDED, true)) {
            return;
        }

        $label = class_basename($model) . ' #' . ($model->getKey() ?? '?');

        // deleted: silinme anındaki tam kopya (adli iz için kritik).
        // updated: sadece değişen alanlar (kompakt).
        // created: yeni satırın tamamı.
        $properties = $verb === 'updated' ? $model->getChanges() : $model->getAttributes();

        $this->logger->log(
            strtolower(class_basename($model)) . ".{$verb}",
            "{$label} " . self::VERB_LABELS[$verb],
            [
                'subject'    => $model,
                'level'      => $level,
                'properties' => $properties,
            ],
        );
    }
}
