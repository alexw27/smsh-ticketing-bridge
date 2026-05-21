<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\ExternalIdentity;

use App\Core\Auth\ExternalIdentity\ExternalIdentityProviderInterface;
use App\Core\Integration\Entity\IntegrationConnection;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class SmartShanghaiExternalIdentityProvider implements ExternalIdentityProviderInterface
{
    public function getProviderKey(): string
    {
        return SmartShanghaiProviderKey::KEY;
    }

    public function getDisplayName(): string
    {
        return 'SmartShanghai';
    }

    public function getIconClass(): string
    {
        return 'ti ti-building-community';
    }

    public function isConnectionConfigured(IntegrationConnection $connection): bool
    {
        return SmartShanghaiConnectionConfig::fromConnection($connection)->isConfigured();
    }

    public function getScopes(): array
    {
        return [];
    }
}
