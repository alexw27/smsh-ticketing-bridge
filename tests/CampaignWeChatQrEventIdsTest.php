<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Ticketing\Domain\CampaignTargetType;
use App\Ticketing\Domain\Entity\Campaign;
use App\Ticketing\Domain\Entity\CampaignTarget;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Campaign\CampaignWeChatQrEventIds;

final class CampaignWeChatQrEventIdsTest extends TestCase
{
    public function testCollectsUniqueEventTargets(): void
    {
        $campaign = new Campaign();
        $campaign->addTarget($this->target(CampaignTargetType::Event, 12345));
        $campaign->addTarget($this->target(CampaignTargetType::Event, 12345));
        $campaign->addTarget($this->target(CampaignTargetType::Event, 7));
        $campaign->addTarget($this->target(CampaignTargetType::Global, null));
        $campaign->addTarget($this->target(CampaignTargetType::Venue, 3));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('find');

        self::assertSame([7, 12345], (new CampaignWeChatQrEventIds($em))->fromCampaign($campaign));
    }

    public function testReturnsEmptyWhenThereIsNoEventTarget(): void
    {
        $campaign = new Campaign();
        $campaign->addTarget($this->target(CampaignTargetType::Global, null));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('find');

        self::assertSame([], (new CampaignWeChatQrEventIds($em))->fromCampaign($campaign));
    }

    private function target(CampaignTargetType $type, ?int $targetId): CampaignTarget
    {
        return (new CampaignTarget())
            ->setTargetType($type)
            ->setTargetId($targetId);
    }
}
