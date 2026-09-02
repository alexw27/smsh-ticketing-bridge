<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use App\Core\Integration\Entity\IntegrationConnection;

final readonly class SmartShanghaiConnectionConfig
{
    public function __construct(
        public string $loginUrl,
        public string $apiBaseUrl,
        public string $apiToken,
        public string $verifyUserPath,
        public string $miniprogramEventPage = '',
        public string $eventBridgePath = '/api2/admin/smtk-event-bridge/{event_id}',
        public string $miniprogramWechatConnectionSlug = 'wechat',
    ) {
    }

    public static function fromConnection(IntegrationConnection $connection): self
    {
        $config = array_merge($connection->getSettings(), $connection->getCredentials());

        return new self(
            loginUrl: trim((string) ($config['login_url'] ?? '')),
            apiBaseUrl: rtrim(trim((string) ($config['api_base_url'] ?? '')), '/'),
            apiToken: trim((string) ($config['api_token'] ?? '')),
            verifyUserPath: trim((string) ($config['verify_user_path'] ?? '/api2/ticketing/users/{user_id}')),
            miniprogramEventPage: trim((string) ($config['miniprogram_event_page'] ?? '')),
            eventBridgePath: trim((string) ($config['event_bridge_path'] ?? '/api2/admin/smtk-event-bridge/{event_id}')),
            miniprogramWechatConnectionSlug: self::normalizeWechatConnectionSlug(
                (string) ($config['miniprogram_wechat_connection_slug'] ?? 'wechat'),
            ),
        );
    }

    public function isConfigured(): bool
    {
        return $this->loginUrl !== ''
            && $this->apiBaseUrl !== ''
            && $this->apiToken !== ''
            && $this->verifyUserPath !== '';
    }

    private static function normalizeWechatConnectionSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return $slug !== '' ? $slug : 'wechat';
    }
}
