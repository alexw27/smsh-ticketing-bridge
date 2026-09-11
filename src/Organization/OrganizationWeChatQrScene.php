<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Organization;

/**
 * WeChat getwxacodeunlimit scene: max 32 visible characters.
 * MiniProgram page {@code pages/org/org} reads {@code id} as the organization id.
 */
final class OrganizationWeChatQrScene
{
    public const MAX_LENGTH = 32;

    public static function fromOrganizationId(int $organizationId): ?string
    {
        if ($organizationId < 1) {
            return null;
        }

        $scene = sprintf('id=%d', $organizationId);
        if (\strlen($scene) > self::MAX_LENGTH) {
            return null;
        }

        return $scene;
    }
}
