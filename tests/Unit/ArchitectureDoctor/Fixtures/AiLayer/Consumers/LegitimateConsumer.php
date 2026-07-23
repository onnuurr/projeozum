<?php

namespace Tests\Fixtures\AiLayer\Consumers;

use Tests\Fixtures\AiLayer\Drivers\GeminiSharedClient;

class LegitimateConsumer
{
    // Paylaşılan alt-katman sınıfı (GeminiSharedClient) hiçbir Contract'a bind
    // edilmediği için burada serbestçe inject edilebilir — bypass değil.
    public function __construct(private GeminiSharedClient $client) {}
}
