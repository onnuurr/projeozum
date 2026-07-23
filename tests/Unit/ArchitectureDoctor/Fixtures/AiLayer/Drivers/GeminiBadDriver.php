<?php

namespace Tests\Fixtures\AiLayer\Drivers;

class GeminiBadDriver
{
    public function __construct(private GeminiSharedClient $client) {}

    public static function make(): self
    {
        // Kendi tanım dosyası içinde "new GeminiBadDriver(" geçse bile bu kendi
        // sürücüsünün istisnası — bypass sayılmamalı.
        return new GeminiBadDriver(new GeminiSharedClient());
    }
}
