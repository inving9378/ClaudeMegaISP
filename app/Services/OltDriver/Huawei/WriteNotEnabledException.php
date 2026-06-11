<?php

namespace App\Services\OltDriver\Huawei;

use RuntimeException;

/**
 * Thrown when any write operation is invoked on HuaweiDriver.
 *
 * Write support is intentionally deferred to block B4, which will introduce
 * an explicit allow-list of SNs (starting with HWTCFEFCC9A2 as test target).
 * This exception surfaces accidental write paths early rather than silently
 * reaching the OLT.
 */
class WriteNotEnabledException extends RuntimeException
{
    public function __construct(string $operation)
    {
        parent::__construct(
            "Write operation '{$operation}' is not enabled on HuaweiDriver. " .
            "Write support arrives in block B4 with allow-list HWTCFEFCC9A2."
        );
    }
}
