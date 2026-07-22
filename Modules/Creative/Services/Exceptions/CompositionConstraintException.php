<?php

namespace Modules\Creative\Services\Exceptions;

use RuntimeException;

/**
 * AI-kompozisyon çıktısı {@see \Modules\Creative\Services\LayoutConstraintEngine}'i
 * geçemediğinde fırlatılır. Geçici hata (PermanentRenderException'dan TÜREMEZ) —
 * GenerateCreativeJob bunu retry eder (bkz. ROADMAP.md Faz J).
 */
class CompositionConstraintException extends RuntimeException
{
}
