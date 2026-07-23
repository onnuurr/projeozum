<?php

namespace Tests\Fixtures\AiLayer\Drivers;

/**
 * Hiçbir Contract'a bind edilmeyen paylaşılan alt-katman sınıfı (gerçek GeminiClient'ın
 * fixture karşılığı) — birden çok sürücüden inject edilir, "sürücü" sayılmamalı.
 */
class GeminiSharedClient
{
}
