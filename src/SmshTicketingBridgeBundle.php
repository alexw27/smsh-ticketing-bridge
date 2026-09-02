<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SmshTicketingBridgeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
