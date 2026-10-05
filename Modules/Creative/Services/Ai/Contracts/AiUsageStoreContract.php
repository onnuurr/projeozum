<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * AI maliyet kaydının yazılacağı yer. AI altyapı katmanı (paylaşılan, domain'e bağımlı olmayan —
 * deptrac Modules_Creative_AiInfra) yalnız bu sözleşmeyi bilir; veritabanı uygulaması Creative
 * domain'indedir (DatabaseAiUsageStore). Bağlı değilse kayıt sessizce atlanır.
 */
interface AiUsageStoreContract
{
    /**
     * @param  array{provider:string, model:string, operation:string, image_size:?string, input_tokens:int,
     *               output_tokens:int, images_out:int, est_cost_usd:float, subject_type:?string, subject_id:mixed}  $row
     */
    public function store(array $row): void;
}
