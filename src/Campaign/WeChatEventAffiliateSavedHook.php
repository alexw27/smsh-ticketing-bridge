<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\EventAffiliateSavedHookInterface;
use App\Ticketing\Domain\Entity\EventAffiliate;

final class WeChatEventAffiliateSavedHook implements EventAffiliateSavedHookInterface
{
    public function __construct(
        private readonly GenerateWeChatEventAffiliateMiniProgramQr $generateWeChatEventAffiliateMiniProgramQr,
    ) {
    }

    public function onEventAffiliateSaved(EventAffiliate $affiliate): void
    {
        $this->generateWeChatEventAffiliateMiniProgramQr->ensure($affiliate);
    }
}
