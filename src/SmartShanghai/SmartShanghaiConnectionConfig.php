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
        public string $publicKey,
        public string $issuer,
        public string $audience,
        public int $clockToleranceSeconds,
    ) {
    }

    public static function fromConnection(IntegrationConnection $connection): self
    {
        $config = array_merge($connection->getSettings(), $connection->getCredentials());

        return new self(
            loginUrl: trim((string) ($config['login_url'] ?? '')),
            apiBaseUrl: rtrim(trim((string) ($config['api_base_url'] ?? '')), '/'),
            apiToken: trim((string) ($config['api_token'] ?? '')),
            verifyUserPath: trim((string) ($config['verify_user_path'] ?? '/ticketing/users/{user_id}')),
            publicKey: trim((string) ($config['public_key'] ?? '')),
            issuer: trim((string) ($config['issuer'] ?? 'smartshanghai')),
            audience: trim((string) ($config['audience'] ?? 'solidsource-ticketing')),
            clockToleranceSeconds: max(0, (int) ($config['clock_tolerance_seconds'] ?? 60)),
        );
    }

    public function isConfigured(): bool
    {
        return $this->loginUrl !== ''
            && $this->apiBaseUrl !== ''
            && $this->apiToken !== ''
            && $this->verifyUserPath !== ''
            && $this->publicKey !== ''
            && $this->issuer !== ''
            && $this->audience !== '';
    }
}
