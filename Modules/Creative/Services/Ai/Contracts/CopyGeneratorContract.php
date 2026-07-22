<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\CopyRequest;

/**
 * Marka tonunda, on-image pazarlama metni (headline/sub/cta) üreten sürücü sözleşmesi.
 *
 * Dönüş: istenen slot anahtarı => üretilen metin. İstenmeyen/üretilemeyen bir
 * anahtar dönüşe hiç konmaz (render onu boş bırakır / statik SVG metni kalır).
 */
interface CopyGeneratorContract
{
    /**
     * @return array<string,string>  slotKey => text
     */
    public function generate(CopyRequest $request): array;
}
