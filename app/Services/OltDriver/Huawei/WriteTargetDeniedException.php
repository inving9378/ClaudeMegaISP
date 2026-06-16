<?php

namespace App\Services\OltDriver\Huawei;

use RuntimeException;

/**
 * Thrown when a write operation targets an ONT whose SN is not in the
 * write allow-list maintained by ReadOnlyGuard.
 *
 * Distinct from WriteNotEnabledException (operation not implemented) —
 * here the operation exists but the specific ONT is not authorized for it.
 */
class WriteTargetDeniedException extends RuntimeException
{
    public function __construct(string $sn, array $allowList)
    {
        parent::__construct(
            "SN '{$sn}' is not in the write allow-list: ["
            . implode(', ', $allowList)
            . "]. Add it to ReadOnlyGuard::WRITE_ALLOW_LIST to authorize it."
        );
    }
}
