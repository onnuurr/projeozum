<?php

namespace Modules\TrainingIngest\Models;

class IngestionLog extends TrainingModel
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ingestion_log';

    protected $fillable = ['source_generation_id', 'status', 'error_detail', 'attempt_count', 'payload_snapshot'];

    protected $casts = [
        'attempt_count'    => 'integer',
        'payload_snapshot' => 'array',
        'created_at'       => 'immutable_datetime',
    ];
}
