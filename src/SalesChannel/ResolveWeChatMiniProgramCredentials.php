<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\Enum\IntegrationProviderKey;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Payment\Entity\PaymentProviderConnection;
use App\Payment\Repository\PaymentProviderConnectionRepository;

final class ResolveWeChatMiniProgramCredentials
{
    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly PaymentProviderConnectionRepository $paymentProviderConnectionRepository,
    ) {
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    public function __invoke(): ?array
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(IntegrationProviderKey::WECHAT->value) as $connection) {
            $credentials = $this->fromIntegrationConnection($connection);
            if ($credentials !== null) {
                return $credentials;
            }
        }

        foreach ($this->paymentProviderConnectionRepository->findEnabledByProvider('wechat_pay') as $connection) {
            $credentials = $this->fromPaymentConnection($connection);
            if ($credentials !== null) {
                return $credentials;
            }
        }

        return null;
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
            'source' => 'integration:' . (string) ($connection->getId() ?? 'unknown'),
        ];
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    private function fromPaymentConnection(PaymentProviderConnection $connection): ?array
    {
        $appId = trim((string) ($connection->getSettings()['app_id'] ?? ''));
        $appSecret = trim((string) ($connection->getCredentials()['miniprogram_app_secret'] ?? ''));
        if ($appId === '' || $appSecret === '') {
            return null;
        }

        return [
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'source' => 'payment:' . (string) ($connection->getId() ?? 'unknown'),
        ];
    }
}
