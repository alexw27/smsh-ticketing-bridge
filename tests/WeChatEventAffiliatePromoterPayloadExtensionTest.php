<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Ticketing\Domain\Entity\EventAffiliate;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Campaign\CampaignWeChatQrPanelView;
use Smsh\TicketingBridge\Campaign\GenerateWeChatEventAffiliateMiniProgramQr;
use Smsh\TicketingBridge\Campaign\WeChatEventAffiliatePromoterPayloadExtension;

final class WeChatEventAffiliatePromoterPayloadExtensionTest extends TestCase
{
    public function testMapsReadyQrView(): void
    {
        $view = new CampaignWeChatQrPanelView(
            CampaignWeChatQrPanelView::STATUS_READY,
            imageUrl: 'https://example.test/qr.png',
            scene: 'id=5614,ea=14',
            page: CampaignWeChatQrPanelView::DEFAULT_CAMPAIGN_PAGE,
        );

        $generator = $this->createMock(GenerateWeChatEventAffiliateMiniProgramQr::class);
        $generator->expects(self::once())->method('ensure')->willReturn($view);

        $extension = new WeChatEventAffiliatePromoterPayloadExtension($generator);
        $extra = $extension->extraFields(new EventAffiliate());

        self::assertSame([
            'wechat_miniprogram_qr' => [
                'status' => 'ready',
                'image_url' => 'https://example.test/qr.png',
                'scene' => 'id=5614,ea=14',
                'page' => 'pages/smtkEvent/smtkEvent',
                'error_message' => null,
                'reason' => null,
            ],
        ], $extra);
    }

    public function testMapsNotConfiguredView(): void
    {
        $generator = $this->createMock(GenerateWeChatEventAffiliateMiniProgramQr::class);
        $generator->method('ensure')->willReturn(CampaignWeChatQrPanelView::notConfigured('credentials'));

        $extension = new WeChatEventAffiliatePromoterPayloadExtension($generator);
        $extra = $extension->extraFields(new EventAffiliate());

        self::assertSame('not_configured', $extra['wechat_miniprogram_qr']['status'] ?? null);
        self::assertSame('credentials', $extra['wechat_miniprogram_qr']['reason'] ?? null);
        self::assertArrayHasKey('image_url', $extra['wechat_miniprogram_qr']);
        self::assertNull($extra['wechat_miniprogram_qr']['image_url']);
    }
}
