<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Organization\OrganizationWeChatQrScene;

final class OrganizationWeChatQrSceneTest extends TestCase
{
    public function testEncodesOrganizationId(): void
    {
        self::assertSame('id=14', OrganizationWeChatQrScene::fromOrganizationId(14));
        self::assertSame('id=1', OrganizationWeChatQrScene::fromOrganizationId(1));
    }

    public function testReturnsNullForNonPositiveIds(): void
    {
        self::assertNull(OrganizationWeChatQrScene::fromOrganizationId(0));
        self::assertNull(OrganizationWeChatQrScene::fromOrganizationId(-1));
    }
}
