<?php

namespace Modules\TrainingIngest\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Bu projede pgcrypto kurulu değil (bkz. Modules/TrainingIngest/database/migrations
 * — id kolonları default'suz uuid). Diğer tüm UUID ihtiyaçlarında olduğu gibi
 * (ör. Modules/Creative TryonResult::generation_token) id PHP tarafında Str::uuid()
 * ile üretilir, DB default'una bırakılmaz.
 */
trait HasUuidPrimaryKey
{
    public static function bootHasUuidPrimaryKey(): void
    {
        static::creating(function ($model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuidPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
