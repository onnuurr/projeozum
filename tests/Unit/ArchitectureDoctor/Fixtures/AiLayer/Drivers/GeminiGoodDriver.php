<?php

namespace Tests\Fixtures\AiLayer\Drivers;

class GeminiGoodDriver
{
    public function __construct(private GeminiSharedClient $client) {}
}
