<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Integration;

use App\Core\Integration\Contract\IntegrationProviderInterface;
use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\ValueObject\IntegrationConfigField;
use App\Core\Integration\ValueObject\IntegrationConnectionTestResult;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class SmartShanghaiIntegrationProvider implements IntegrationProviderInterface
{
    public function getProviderKey(): string
    {
        return SmartShanghaiProviderKey::KEY;
    }

    public function getDisplayName(): string
    {
        return 'SmartShanghai';
    }

    public function getDescription(): string
    {
        return 'External identity login for SmartShanghai users.';
    }

    public function supportsConnectionTesting(): bool
    {
        return true;
    }

    /**
     * @return list<IntegrationConfigField>
     */
    public function getConfigurationSchema(): array
    {
        return [
            new IntegrationConfigField(
                key: 'login_url',
                label: 'Login URL',
                type: 'url',
                section: 'settings',
                required: true,
                helpText: 'SmartShanghai login URL. The bridge appends callback_url to this URL.',
            ),
            new IntegrationConfigField(
                key: 'api_base_url',
                label: 'API base URL',
                type: 'url',
                section: 'settings',
                required: true,
            ),
            new IntegrationConfigField(
                key: 'api_token',
                label: 'API token',
                type: 'password',
                section: 'credentials',
                required: true,
                sensitive: true,
            ),
            new IntegrationConfigField(
                key: 'public_key',
                label: 'JWT public key',
                type: 'textarea',
                section: 'credentials',
                required: true,
                sensitive: true,
                helpText: 'PEM public key used to verify RS256 SmartShanghai login assertion JWTs.',
            ),
            new IntegrationConfigField(
                key: 'issuer',
                label: 'JWT issuer',
                type: 'text',
                section: 'settings',
                required: true,
                helpText: 'Expected iss claim. Defaults to smartshanghai.',
            ),
            new IntegrationConfigField(
                key: 'audience',
                label: 'JWT audience',
                type: 'text',
                section: 'settings',
                required: true,
                helpText: 'Expected aud claim. Defaults to solidsource-ticketing.',
            ),
            new IntegrationConfigField(
                key: 'clock_tolerance_seconds',
                label: 'JWT clock tolerance seconds',
                type: 'integer',
                section: 'settings',
                required: false,
                helpText: 'Allowed clock skew for exp/nbf checks. Defaults to 60.',
            ),
            new IntegrationConfigField(
                key: 'verify_user_path',
                label: 'Verify user path',
                type: 'text',
                section: 'settings',
                required: false,
                helpText: 'Optional. Defaults to /ticketing/users/{user_id}.',
            ),
        ];
    }

    public function testConnection(IntegrationConnection $connection): IntegrationConnectionTestResult
    {
        $config = SmartShanghaiConnectionConfig::fromConnection($connection);
        if (!$config->isConfigured()) {
            return IntegrationConnectionTestResult::failure('SmartShanghai bridge is not fully configured.');
        }

        return IntegrationConnectionTestResult::success('SmartShanghai bridge credentials are configured.');
    }
}
