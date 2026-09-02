<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Integration;

use App\Core\Integration\Contract\IntegrationProviderInterface;
use App\Core\Integration\Contract\OutboundWebhookRequestConfiguratorInterface;
use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\ValueObject\OutboundWebhookRequest;
use App\Core\Integration\ValueObject\IntegrationConfigField;
use App\Core\Integration\ValueObject\IntegrationConnectionTestResult;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class SmartShanghaiIntegrationProvider implements IntegrationProviderInterface, OutboundWebhookRequestConfiguratorInterface
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
                label: 'API key',
                type: 'password',
                section: 'credentials',
                required: true,
                sensitive: true,
                helpText: 'Partner API key sent as the key query parameter on every SmartShanghai API call.',
            ),
            new IntegrationConfigField(
                key: 'verify_user_path',
                label: 'Verify user path',
                type: 'text',
                section: 'settings',
                required: false,
                helpText: 'Optional. Defaults to /api2/ticketing/users/{user_id}. SmartShanghai validates the JWT.',
            ),
            new IntegrationConfigField(
                key: 'miniprogram_event_page',
                label: 'MiniProgram event page path',
                type: 'text',
                section: 'settings',
                required: false,
                helpText: 'Optional. Unused for campaign and event-affiliate QRs; those always open pages/smtkEvent/smtkEvent.',
            ),
            new IntegrationConfigField(
                key: 'miniprogram_wechat_connection_slug',
                label: 'MiniProgram WeChat connection slug',
                type: 'text',
                section: 'settings',
                required: false,
                helpText: 'Optional. Leave empty to use WeChat Pay MiniProgram credentials (same App ID and secret as MiniProgram checkout). Set a WeChat integration API slug to override. Use wechat-scanner only if these QRs should open the door-scanner MiniProgram.',
            ),
            new IntegrationConfigField(
                key: 'event_bridge_path',
                label: 'Event bridge API path',
                type: 'text',
                section: 'settings',
                required: false,
                helpText: 'Optional. Defaults to /api2/admin/smtk-event-bridge/{event_id}. Used when publishing events to link SmartShanghai listings.',
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

    public function configureOutboundWebhookRequest(
        IntegrationConnection $connection,
        OutboundWebhookRequest $request,
    ): OutboundWebhookRequest {
        $config = SmartShanghaiConnectionConfig::fromConnection($connection);
        if ($config->apiToken === '') {
            return $request;
        }

        $options = $request->options;
        $query = $options['query'] ?? [];
        if (!is_array($query)) {
            $query = [];
        }

        $query['key'] = $config->apiToken;
        $options['query'] = $query;

        return $request->withOptions($options);
    }
}
