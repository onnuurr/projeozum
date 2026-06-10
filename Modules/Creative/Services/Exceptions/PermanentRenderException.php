<?php

namespace Modules\Creative\Services\Exceptions;

use RuntimeException;

/**
 * Yeniden denemenin anlamsız olduğu kalıcı render hatası.
 *
 * Örn. şablon/ürün bulunamadı, geçersiz şablon. Geçici hatalar (AI/render
 * timeout, ağ) düz Throwable olarak fırlatılır ve Job tarafından retry edilir.
 */
class PermanentRenderException extends RuntimeException
{
}
