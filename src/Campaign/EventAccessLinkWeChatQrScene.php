<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Domain\Entity\EventAccessLink;

/**
 * WeChat getwxacodeunlimit scene: max 32 visible characters.
 * MiniProgram page {@code pages/ticketingSchedule/ticketingSchedule} reads {@code id} as the
 * SmartTicket event id and {@code access} as the access-link slug for {@code ?access=}.
 * WeChat rejects {@code &} in scene ({@code invalid scene}); pairs are comma-separated.
 */
final class EventAccessLinkWeChatQrScene
{
    public const MAX_LENGTH = 32;

    public static function fromAccessLink(EventAccessLink $accessLink): ?string
    {
        $eventId = $accessLink->getEvent()?->getId();
        $slug = trim($accessLink->getSlug());
        if ($eventId === null || $eventId < 1 || $slug === '') {
            return null;
        }

        $scene = sprintf('id=%d,access=%s', $eventId, $slug);
        if (\strlen($scene) > self::MAX_LENGTH) {
            return null;
        }

        return $scene;
    }
}
