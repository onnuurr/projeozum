<?php

namespace Modules\Creative\Services\Vision;

interface TextRecognizerContract
{
    /**
     * Görseldeki metni kelime/kutu düzeyinde tanır.
     *
     * @return array<int,array{text:string,x:int,y:int,w:int,h:int,confidence:float}>
     */
    public function recognize(string $imagePath): array;
}
