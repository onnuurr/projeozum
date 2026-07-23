<?php

namespace Tests\Fixtures\AiLayer\Consumers;

use Tests\Fixtures\AiLayer\Drivers\GeminiBadDriver;

class BypassingConsumer
{
    // Contract yerine somut sürücüye doğrudan bağımlılık — asıl tespit edilmesi
    // gereken ihlal budur.
    public function handle(): object
    {
        return new GeminiBadDriver();
    }
}
