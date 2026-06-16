<?php

namespace App\Services\OltDriver\Huawei;

use RuntimeException;

/**
 * Thrown by HuaweiDriver::executeSteps() when any write step receives a VRP
 * error response (contains "Error", "Failure", or "failed").
 *
 * The exception carries the failing command, the raw OLT response, and the
 * transcript of steps that did complete — enough for a caller to log, alert,
 * and attempt rollback.
 *
 * ⚠️ Caught at the withWriteTarget() / authorizeOnu() boundary and converted
 * to ['success' => false, 'aborted_at' => ...] so the public driver API always
 * returns an array. The throw exists so the abort is auditable at every layer.
 */
class WriteExecutionAbortedException extends RuntimeException
{
    /**
     * @param array<array{cmd:string,response:string}> $completedSteps Steps that ran before the abort
     */
    public function __construct(
        public readonly string $failedCmd,
        public readonly string $rawResponse,
        public readonly array  $completedSteps = [],
    ) {
        parent::__construct(
            "Write aborted at '{$failedCmd}': " . substr(trim($rawResponse), 0, 200)
        );
    }
}
