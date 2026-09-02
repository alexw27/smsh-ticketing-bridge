<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\Repository\IntegrationConnectionRepository;

final class ResolveWeChatMiniProgramCredentials
{
    /**
     * SmartShanghai scanner MiniProgram integration (System → API / Integrations).
     * Slug is stable across environments; do not hard-code connection IDs.
     */
    public const SCANNER_CONNECTION_SLUG = 'wechat-scanner';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
    ) {
    }

    /**
     * @return array{app_id: string, app_secret: string, source: string}|null
     */
    public function __invoke(): ?array
    {
        $connection = $this->integrationConnectionRepository->findEnabledBySlug(self::SCANNER_CONNECTION_SLUG);
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
            'source' => 'integration:' . self::SCANNER_CONNECTION_SLUG,
        ];
    }
}
