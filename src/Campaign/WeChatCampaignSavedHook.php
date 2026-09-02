<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\CampaignSavedHookInterface;
use App\Ticketing\Domain\Entity\Campaign;

final class WeChatCampaignSavedHook implements CampaignSavedHookInterface
{
    public function __construct(
        private readonly GenerateWeChatCampaignMiniProgramQr $generateWeChatCampaignMiniProgramQr,
    ) {
    }

    public function onCampaignSaved(Campaign $campaign): void
    {
        $this->generateWeChatCampaignMiniProgramQr->ensure($campaign);
    }
}
