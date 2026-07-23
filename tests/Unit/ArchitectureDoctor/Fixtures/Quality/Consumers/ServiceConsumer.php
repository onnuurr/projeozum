<?php

namespace Tests\Fixtures\Quality\Consumers;

use Tests\Fixtures\Quality\Services\UsedService;

class ServiceConsumer
{
    public function __construct(private UsedService $service) {}

    public function makeAnother(): UsedService
    {
        return new UsedService();
    }
}
