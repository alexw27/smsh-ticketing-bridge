<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Ticketing\Domain\Entity\Event;
use App\Ticketing\Domain\Entity\EventAccessLink;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Campaign\EventAccessLinkWeChatQrScene;

final class EventAccessLinkWeChatQrSceneTest extends TestCase
{
    public function testEncodesEventIdAndAccessSlug(): void
    {
        self::assertSame(
            'id=5952,access=vip',
            EventAccessLinkWeChatQrScene::fromAccessLink($this->accessLink(5952, 'vip')),
        );
        self::assertSame(
            'id=5907,access=partner-a',
            EventAccessLinkWeChatQrScene::fromAccessLink($this->accessLink(5907, 'partner-a')),
        );
    }

    public function testReturnsNullWithoutEventOrSlug(): void
    {
        self::assertNull(EventAccessLinkWeChatQrScene::fromAccessLink(new EventAccessLink()));
        self::assertNull(EventAccessLinkWeChatQrScene::fromAccessLink($this->accessLink(5952, '')));
    }

    public function testReturnsNullWhenSceneExceedsWeChatLimit(): void
    {
        // id=5952,access= = 15 chars; leave room for only 17 slug chars within 32.
        $longSlug = str_repeat('a', 18);
        self::assertNull(EventAccessLinkWeChatQrScene::fromAccessLink($this->accessLink(5952, $longSlug)));
    }

    private function accessLink(int $eventId, string $slug): EventAccessLink
    {
        $event = new Event();
        (new \ReflectionProperty(Event::class, 'id'))->setValue($event, $eventId);

        return (new EventAccessLink())
            ->setEvent($event)
            ->setSlug($slug);
    }
}
