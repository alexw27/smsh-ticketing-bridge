<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use App\Core\Auth\Entity\User;
use App\Core\Auth\Entity\UserExternalIdentity;
use App\Core\Auth\Repository\UserExternalIdentityRepository;
use App\Core\Communication\Entity\OutboundMessageLog;
use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Core\Log\Service\LogDispatcher;
use App\Core\Routing\PublicUrlGenerator;
use App\Ticketing\Contract\OrderRefundBuyerNotificationHookInterface;
use App\Ticketing\Domain\Entity\Refund;
use App\Ticketing\Domain\Entity\TicketOrder;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

/**
 * After a successful refund with buyer notification enabled, tell SmartShanghai
 * to send the buyer the cancellation message.
 */
final class NotifySmartShanghaiBuyerOfOrderRefund implements OrderRefundBuyerNotificationHookInterface
{
    public const CHANNEL = 'smartshanghai';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly UserExternalIdentityRepository $userExternalIdentityRepository,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly SendSmartShanghaiTicketingCancellation $sendSmartShanghaiTicketingCancellation,
        private readonly LogDispatcher $logDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyBuyerOfRefund(Refund $refund): void
    {
        $connection = $this->resolveConnection();
        if (!$connection instanceof IntegrationConnection) {
            $this->logger->debug('ticketing.smartshanghai_cancellation.skipped_not_configured', [
                'refund_id' => $refund->getId(),
            ]);

            return;
        }

        $order = $refund->getOrder();
        if (!$order instanceof TicketOrder) {
            return;
        }

        $orderId = $order->getId();
        $user = $order->getUser();
        if ($orderId === null || !$user instanceof User || $user->getId() === null) {
            $this->logger->debug('ticketing.smartshanghai_cancellation.skipped_no_user', [
                'refund_id' => $refund->getId(),
                'order_id' => $orderId,
            ]);

            return;
        }

        $identity = $this->userExternalIdentityRepository->findOneBy([
            'user' => $user,
            'providerKey' => SmartShanghaiProviderKey::KEY,
        ]);
        if (!$identity instanceof UserExternalIdentity) {
            $this->logger->debug('ticketing.smartshanghai_cancellation.skipped_no_identity', [
                'refund_id' => $refund->getId(),
                'order_id' => $orderId,
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $smartShanghaiUserId = trim($identity->getProviderUserId());
        if ($smartShanghaiUserId === '') {
            return;
        }

        $message = trim((string) ($refund->getReasonNote() ?? ''));
        $orderNumber = $order->getOrderNumber();
        $orderUrl = $this->publicUrlGenerator->orderPortalUrl($order->getPublicAccessToken()) ?? '';
        $config = SmartShanghaiConnectionConfig::fromConnection($connection);

        try {
            ($this->sendSmartShanghaiTicketingCancellation)(
                $config,
                $smartShanghaiUserId,
                $message,
                $orderNumber,
                $orderUrl,
                $orderId,
            );
            $successful = true;
            $failureReason = null;
        } catch (\Throwable $exception) {
            $successful = false;
            $failureReason = $exception->getMessage();
            $this->logger->error('ticketing.smartshanghai_cancellation.failed', [
                'refund_id' => $refund->getId(),
                'order_id' => $orderId,
                'smartshanghai_user_id' => $smartShanghaiUserId,
                'exception' => $exception::class,
                'message' => $failureReason,
            ]);
        }

        $this->logOutbound(
            $connection,
            $user,
            $smartShanghaiUserId,
            $message,
            $orderId,
            $orderNumber,
            $orderUrl,
            $refund->getId(),
            $successful,
            $failureReason,
        );
    }

    private function resolveConnection(): ?IntegrationConnection
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY) as $connection) {
            if (SmartShanghaiConnectionConfig::fromConnection($connection)->isConfigured()) {
                return $connection;
            }
        }

        return null;
    }

    private function logOutbound(
        IntegrationConnection $connection,
        User $user,
        string $smartShanghaiUserId,
        string $message,
        int $orderId,
        string $orderNumber,
        string $orderUrl,
        ?int $refundId,
        bool $successful,
        ?string $failureReason,
    ): void {
        try {
            $this->logDispatcher->outbound(
                channel: self::CHANNEL,
                recipientIdentifier: mb_substr($smartShanghaiUserId, 0, 190),
                status: $successful ? OutboundMessageLog::STATUS_SENT : OutboundMessageLog::STATUS_FAILED,
                payload: [
                    'provider' => $connection->getProviderKey(),
                    'connection_id' => $connection->getId(),
                    'connection_name' => $connection->getName(),
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'order_url' => $orderUrl,
                    'refund_id' => $refundId,
                    'smartshanghai_user_id' => $smartShanghaiUserId,
                    'endpoint' => SendSmartShanghaiTicketingCancellation::PATH,
                ],
                user: $user,
                sentAt: $successful ? new \DateTimeImmutable() : null,
                failureReason: $failureReason,
                subject: 'Ticketing cancellation',
                textBody: $message,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('ticketing.smartshanghai_cancellation.log_failed', [
                'refund_id' => $refundId,
                'order_id' => $orderId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
