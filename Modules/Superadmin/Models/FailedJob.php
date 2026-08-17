<?php

namespace Modules\Superadmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Laravel'in standart `failed_jobs` tablosu (queue:work üretir) — bu modül
 * migration'ını taşımaz, çekirdek framework'ün varsayılan şemasına salt-okunur
 * bir pencere açar. Bkz. FailedJobService (retry/flush) ve FailedJobController.
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $casts = [
        'failed_at' => 'datetime',
    ];
}
