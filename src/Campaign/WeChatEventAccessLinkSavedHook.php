<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\EventAccessLinkSavedHookInterface;
use App\Ticketing\Domain\Entity\EventAccessLink;

final class WeChatEventAccessLinkSavedHook implements EventAccessLinkSavedHookInterface
{
    public function __construct(
        private readonly GenerateWeChatEventAccessLinkMiniProgramQr $generateWeChatEventAccessLinkMiniProgramQr,
    ) {
    }

    public function onEventAccessLinkSaved(EventAccessLink $accessLink): void
    {
        $this->generateWeChatEventAccessLinkMiniProgramQr->ensure($accessLink);
    }
}
