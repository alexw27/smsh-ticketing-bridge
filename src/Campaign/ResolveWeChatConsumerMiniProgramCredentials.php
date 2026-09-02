<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\Enum\IntegrationProviderKey;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Payment\Entity\PaymentProviderConnection;
use Doctrine\ORM\EntityManagerInterface;
use Smsh\TicketingBridge\SalesChannel\ResolveWeChatMiniProgramCredentials;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;

final class ResolveWeChatConsumerMiniProgramCredentials
{
    public const DEFAULT_CONNECTION_SLUG = 'wechat';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Credentials for the customer-facing MiniProgram (not the door scanner).
     *
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    public function __invoke(?SmartShanghaiConnectionConfig $config = null): ?array
    {
        $preferredSlug = trim($config?->miniprogramWechatConnectionSlug ?? '');
        if ($preferredSlug !== '') {
            $preferred = $this->fromSlug($preferredSlug);
            if ($preferred !== null) {
                return $preferred;
            }
        }

        $fromPay = $this->fromWeChatPay();
        if ($fromPay !== null) {
            return $fromPay;
        }

        $fromDefaultIntegration = $this->fromSlug(self::DEFAULT_CONNECTION_SLUG);
        if ($fromDefaultIntegration !== null) {
            return $fromDefaultIntegration;
        }

        foreach ($this->integrationConnectionRepository->findEnabledByProvider(IntegrationProviderKey::WECHAT->value) as $connection) {
            if ($connection->getConnectionSlug() === ResolveWeChatMiniProgramCredentials::SCANNER_CONNECTION_SLUG) {
                continue;
            }

            $credentials = $this->fromIntegrationConnection($connection);
            if ($credentials !== null) {
                return $credentials;
            }
        }

        return null;
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    private function fromWeChatPay(): ?array
    {
        $connections = $this->entityManager->getRepository(PaymentProviderConnection::class)->findBy(
            ['providerKey' => 'wechat_pay', 'isEnabled' => true],
            ['name' => 'ASC', 'id' => 'ASC'],
        );

        foreach ($connections as $connection) {
            if (!$connection instanceof PaymentProviderConnection) {
                continue;
            }

            $appId = trim((string) ($connection->getSettings()['app_id'] ?? ''));
            $appSecret = trim((string) ($connection->getCredentials()['miniprogram_app_secret'] ?? ''));
            if ($appId === '' || $appSecret === '') {
                continue;
            }

            return [
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'source' => 'payment:wechat_pay',
            ];
        }

        return null;
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    private function fromSlug(string $slug): ?array
    {
        $connection = $this->integrationConnectionRepository->findEnabledBySlug($slug);
        if ($connection === null) {
            return null;
        }

        return $this->fromIntegrationConnection($connection);
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    private function fromIntegrationConnection(IntegrationConnection $connection): ?array
    {
        $appId = trim((string) ($connection->getSettings()['app_id'] ?? ''));
        $appSecret = trim((string) ($connection->getCredentials()['miniprogram_app_secret'] ?? ''));
        if ($appId === '' || $appSecret === '') {
            return null;
        }

        return [
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'source' => 'integration:'.$connection->getConnectionSlug(),
        ];
    }
}
