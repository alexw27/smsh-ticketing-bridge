<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Ticketing\Domain\Entity\Event;
use App\Ticketing\Domain\Entity\EventAffiliate;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Campaign\EventAffiliateWeChatQrScene;

final class EventAffiliateWeChatQrSceneTest extends TestCase
{
    public function testEncodesEventIdAndAffiliateId(): void
    {
        self::assertSame('id=5753,ea=14', EventAffiliateWeChatQrScene::fromAffiliate($this->affiliate(14, 5753)));
        self::assertSame('id=5745,ea=2', EventAffiliateWeChatQrScene::fromAffiliate($this->affiliate(2, 5745)));
    }

    public function testReturnsNullWithoutIds(): void
    {
        self::assertNull(EventAffiliateWeChatQrScene::fromAffiliate(new EventAffiliate()));
    }

    private function affiliate(int $affiliateId, int $eventId): EventAffiliate
    {
        $event = new Event();
        (new \ReflectionProperty(Event::class, 'id'))->setValue($event, $eventId);

        $affiliate = (new EventAffiliate())->setEvent($event);
        (new \ReflectionProperty(EventAffiliate::class, 'id'))->setValue($affiliate, $affiliateId);

        return $affiliate;
    }
}
