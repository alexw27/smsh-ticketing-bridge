<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

/**
 * WeChat getwxacodeunlimit scene: max 32 visible characters.
 * MiniProgram page {@code pages/smtkEvent/smtkEvent} reads {@code id} as the
 * SmartTicket event id and {@code aci} as {@code affiliate_campaign_id}.
 */
final class CampaignWeChatQrScene
{
    public const MAX_LENGTH = 32;

    public static function fromEventAndCampaign(int $eventId, int $campaignId): ?string
    {
        if ($eventId < 1 || $campaignId < 1) {
            return null;
        }

        $scene = sprintf('id=%d&aci=%d', $eventId, $campaignId);
        if (\strlen($scene) > self::MAX_LENGTH) {
            return null;
        }

        return $scene;
    }
}
