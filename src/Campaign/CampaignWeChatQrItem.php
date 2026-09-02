<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

final readonly class CampaignWeChatQrItem
{
    public function __construct(
        public string $imageUrl,
        public string $scene,
        public string $page,
        public int $eventId,
        public ?string $eventLabel = null,
    ) {
    }
}
