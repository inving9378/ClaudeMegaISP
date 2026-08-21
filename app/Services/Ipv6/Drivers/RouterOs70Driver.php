<?php

namespace App\Services\Ipv6\Drivers;

/** RouterOS 7.0 a 7.12. Sintaxis base heredada de AbstractRouterOsDriver, sin diferencias conocidas. */
class RouterOs70Driver extends AbstractRouterOsDriver
{
    public function nombre(): string
    {
        return 'RouterOS 7.0-7.12';
    }
}
