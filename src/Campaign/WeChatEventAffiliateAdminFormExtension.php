<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\EventAffiliateAdminFormExtensionInterface;
use App\Ticketing\Domain\Entity\EventAffiliate;

final class WeChatEventAffiliateAdminFormExtension implements EventAffiliateAdminFormExtensionInterface
{
    public function __construct(
        private readonly GenerateWeChatEventAffiliateMiniProgramQr $generateWeChatEventAffiliateMiniProgramQr,
    ) {
    }

    public function panels(EventAffiliate $affiliate, bool $isNew): array
    {
        if ($isNew) {
            return [];
        }

        return [[
            'template' => '@SmshTicketingBridge/admin/events/wechat_event_affiliate_qr_panel.html.twig',
            'context' => [
                'wechat_event_affiliate_qr' => $this->generateWeChatEventAffiliateMiniProgramQr->ensure($affiliate),
            ],
        ]];
    }
}
