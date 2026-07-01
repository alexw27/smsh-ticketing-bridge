<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Bootstrap;

use App\Ticketing\Domain\ClientPaymentProfile;
use App\Ticketing\Domain\Entity\SalesChannel;
use App\Ticketing\Domain\Repository\SalesChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Smsh\TicketingBridge\SalesChannel\SmshTicketingBridgeSalesChannel;

final class EnsureWeChatScannerSalesChannel
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(): bool
    {
        if ($this->salesChannelRepository->findOneByCode(SmshTicketingBridgeSalesChannel::CODE_WECHAT_SCANNER) !== null) {
            return false;
        }

        $channel = (new SalesChannel())
            ->setCode(SmshTicketingBridgeSalesChannel::CODE_WECHAT_SCANNER)
            ->setName('WeChat Scanner')
            ->setClientPaymentProfile(ClientPaymentProfile::WebRedirect);
        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return true;
    }
}
