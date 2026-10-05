<?php

namespace Modules\Creative\Services;

use Modules\Creative\Models\AiUsage;
use Modules\Creative\Services\Ai\Contracts\AiUsageStoreContract;

/** AI maliyet kayıtlarını creative_ai_usages tablosuna yazar (bkz. AiUsageRecorder). */
class DatabaseAiUsageStore implements AiUsageStoreContract
{
    public function store(array $row): void
    {
        AiUsage::create($row);
    }
}
