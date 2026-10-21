<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

use App\Core\Integration\Repository\IntegrationConnectionRepository;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;

/**
 * Resolves the MiniProgram access_token for the SmartShanghai wechat-market connection.
 */
final class ResolveWeChatMarketAccessToken
{
    public const CONNECTION_SLUG = 'wechat-market';

    public const ERROR_NOT_CONFIGURED = 'wechat_market.not_configured';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
    ) {
    }

    public function resolve(?string $providedAccessToken): string
    {
        $providedAccessToken = $providedAccessToken !== null ? trim($providedAccessToken) : '';
        if ($providedAccessToken !== '') {
            return $providedAccessToken;
        }

        $connection = $this->integrationConnectionRepository->findEnabledBySlug(self::CONNECTION_SLUG);
        if ($connection === null) {
            throw new \DomainException(self::ERROR_NOT_CONFIGURED);
        }

        $appId = trim((string) ($connection->getSettings()['app_id'] ?? ''));
        $appSecret = trim((string) ($connection->getCredentials()['miniprogram_app_secret'] ?? ''));
        if ($appId === '' || $appSecret === '') {
            throw new \DomainException(self::ERROR_NOT_CONFIGURED);
        }

        try {
            return $this->weChatMiniProgramAccessToken->fetch($appId, $appSecret);
        } catch (WeChatMiniProgramPublisherException $exception) {
            throw new WeChatPhoneNumberExchangeException(
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Could not fetch a WeChat MiniProgram access_token.',
                previous: $exception,
            );
        }
    }
}
