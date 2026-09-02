<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\CampaignAdminFormExtensionInterface;
use App\Ticketing\Domain\Entity\Campaign;

final class WeChatCampaignAdminFormExtension implements CampaignAdminFormExtensionInterface
{
    public function __construct(
        private readonly GenerateWeChatCampaignMiniProgramQr $generateWeChatCampaignMiniProgramQr,
    ) {
    }

    public function panels(Campaign $campaign, bool $isNew): array
    {
        if ($isNew) {
            return [];
        }

        return [[
            'template' => '@SmshTicketingBridge/admin/campaigns/wechat_qr_panel.html.twig',
            'context' => [
                'wechat_campaign_qr' => $this->generateWeChatCampaignMiniProgramQr->ensure($campaign),
            ],
        ]];
    }
}
