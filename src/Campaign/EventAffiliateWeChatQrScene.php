<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Domain\Entity\EventAffiliate;

/**
 * WeChat getwxacodeunlimit scene: max 32 visible characters.
 * `&` is documented as allowed but WeChat rejects it (`invalid scene`);
 * use commas between pairs. MiniProgram event page reads {@code id} as the
 * event id and {@code ea} as {@code event_affiliate_id} for checkout.
 */
final class EventAffiliateWeChatQrScene
{
    public const MAX_LENGTH = 32;

    public static function fromAffiliate(EventAffiliate $affiliate): ?string
    {
        $affiliateId = $affiliate->getId();
        $eventId = $affiliate->getEvent()?->getId();
        if ($affiliateId === null || $eventId === null || $affiliateId < 1 || $eventId < 1) {
            return null;
        }

        $scene = sprintf('id=%d,ea=%d', $eventId, $affiliateId);
        if (\strlen($scene) > self::MAX_LENGTH) {
            return null;
        }

        return $scene;
    }
}
