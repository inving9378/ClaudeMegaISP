<?php

namespace App\Services\OltDriver\Huawei;

use RuntimeException;

class ReadOnlyViolationException extends RuntimeException
{
    public function __construct(string $command)
    {
        parent::__construct(
            "OLT read-only guard rejected command [{$command}]. "
            . 'Only whitelisted display/navigation commands are permitted.'
        );
    }
}
