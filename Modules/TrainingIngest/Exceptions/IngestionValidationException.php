<?php

namespace Modules\TrainingIngest\Exceptions;

use RuntimeException;

/**
 * ADIM 3 doğrulama kurallarından birinin ihlali (bkz. IngestionService). Sessizce
 * yutulmaz: hem event listener hem backfill CLI bunu training.ingestion_log'a
 * status='failed' olarak yazar.
 */
class IngestionValidationException extends RuntimeException
{
}
