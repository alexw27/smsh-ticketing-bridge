<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Domain\CampaignTargetType;
use App\Ticketing\Domain\Entity\Campaign;
use App\Ticketing\Domain\Entity\PriceCategory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Event ids a campaign QR can open. Venue and global targets have no event page.
 */
final class CampaignWeChatQrEventIds
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<int>
     */
    public function fromCampaign(Campaign $campaign): array
    {
        /** @var array<int, true> $eventIds */
        $eventIds = [];

        foreach ($campaign->getTargets() as $target) {
            match ($target->getTargetType()) {
                CampaignTargetType::Event => $this->add($eventIds, $target->getTargetId()),
                CampaignTargetType::PriceCategory => $this->addFromPriceCategory($eventIds, $target->getTargetId()),
                default => null,
            };
        }

        $ids = array_keys($eventIds);
        sort($ids);

        return $ids;
    }

    /**
     * @param array<int, true> $eventIds
     */
    private function add(array &$eventIds, ?int $eventId): void
    {
        if ($eventId !== null && $eventId > 0) {
            $eventIds[$eventId] = true;
        }
    }

    /**
     * @param array<int, true> $eventIds
     */
    private function addFromPriceCategory(array &$eventIds, ?int $priceCategoryId): void
    {
        if ($priceCategoryId === null || $priceCategoryId < 1) {
            return;
        }

        $priceCategory = $this->entityManager->find(PriceCategory::class, $priceCategoryId);
        $this->add($eventIds, $priceCategory?->getEvent()?->getId());
    }
}
