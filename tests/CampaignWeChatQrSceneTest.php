<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Campaign\CampaignWeChatQrScene;

final class CampaignWeChatQrSceneTest extends TestCase
{
    public function testEncodesEventIdAndCampaignId(): void
    {
        self::assertSame('id=5614&aci=99', CampaignWeChatQrScene::fromEventAndCampaign(5614, 99));
        self::assertSame('id=12345&aci=99', CampaignWeChatQrScene::fromEventAndCampaign(12345, 99));
    }

    public function testReturnsNullForNonPositiveIds(): void
    {
        self::assertNull(CampaignWeChatQrScene::fromEventAndCampaign(0, 99));
        self::assertNull(CampaignWeChatQrScene::fromEventAndCampaign(12345, 0));
    }
}
