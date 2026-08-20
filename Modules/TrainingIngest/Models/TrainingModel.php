<?php

namespace Modules\TrainingIngest\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\TrainingIngest\Models\Concerns\HasUuidPrimaryKey;

/**
 * training.* tablolarının ortak temeli — hepsi 'training' bağlantısına (search_path
 * training) bağlıdır ve UUID birincil anahtar kullanır. Append-only: bu şemada hiçbir
 * model UPDATE/DELETE yapmaz (düzeltme = yeni satır + superseded_by, bkz.
 * training.reviews); o disiplin çağıran servis kodunda korunur, model seviyesinde
 * zorlanmaz (basit tutulur — bu bir domain kısıtı, framework kısıtı değil).
 */
abstract class TrainingModel extends Model
{
    use HasUuidPrimaryKey;

    protected $connection = 'training';

    public $timestamps = false;
}
