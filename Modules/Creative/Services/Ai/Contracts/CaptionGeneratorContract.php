<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\CaptionRequest;

/**
 * Marka tonunda sosyal medya caption + hashtag üreten sürücü sözleşmesi.
 */
interface CaptionGeneratorContract
{
    /**
     * @return array{caption:string,hashtags:array<int,string>}
     */
    public function generate(CaptionRequest $request): array;
}
