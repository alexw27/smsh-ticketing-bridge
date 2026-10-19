<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\EventAccessLinkAdminFormExtensionInterface;
use App\Ticketing\Domain\Entity\EventAccessLink;

final class WeChatEventAccessLinkAdminFormExtension implements EventAccessLinkAdminFormExtensionInterface
{
    public function __construct(
        private readonly GenerateWeChatEventAccessLinkMiniProgramQr $generateWeChatEventAccessLinkMiniProgramQr,
    ) {
    }

    public function panels(EventAccessLink $accessLink, bool $isNew): array
    {
        if ($isNew) {
            return [];
        }

        return [[
            'template' => '@SmshTicketingBridge/admin/events/wechat_event_access_link_qr_panel.html.twig',
            'context' => [
                'wechat_event_access_link_qr' => $this->generateWeChatEventAccessLinkMiniProgramQr->ensure($accessLink),
            ],
        ]];
    }
}
