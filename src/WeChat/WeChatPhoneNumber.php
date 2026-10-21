<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

final readonly class WeChatPhoneNumber
{
    public function __construct(
        public string $phoneNumber,
        public string $purePhoneNumber,
        public string $countryCode,
    ) {
    }
}
