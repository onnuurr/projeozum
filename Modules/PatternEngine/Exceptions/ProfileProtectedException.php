<?php

namespace Modules\PatternEngine\Exceptions;

use RuntimeException;

/** `ts_default` gibi repo'ya gömülü referans profiller API ile değiştirilemez. */
class ProfileProtectedException extends RuntimeException {}
