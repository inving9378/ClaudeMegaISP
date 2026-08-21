<?php

namespace App\Services\Ipv6\Drivers;

/** RouterOS 6.x. Sintaxis base heredada de AbstractRouterOsDriver, sin diferencias conocidas. */
class RouterOs6xDriver extends AbstractRouterOsDriver
{
    public function nombre(): string
    {
        return 'RouterOS 6.x';
    }
}
