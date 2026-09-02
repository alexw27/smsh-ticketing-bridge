<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

/**
 * WeChat getwxacodeunlimit scene: max 32 visible characters.
 * MiniProgram event page reads {@code id} as the event id and {@code aci}
 * as {@code affiliate_campaign_id} for checkout.
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
