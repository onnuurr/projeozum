<?php

namespace Modules\Bagisto\Exceptions;

use RuntimeException;

/**
 * İade akışında iş kuralı ihlali (yanlış durumda karar, fatura şartı karşılanmadan para iadesi,
 * iade tutarı üst sınırı aşımı, ödeme sağlayıcısı hatası). Mesaj kullanıcıya gösterilir.
 */
class OrderReturnException extends RuntimeException {}
