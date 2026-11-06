<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

use App\Core\Integration\Contract\WeChatSubscribeMessageSenderInterface;
use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\ValueObject\IntegrationOperationResult;

/**
 * Communication-flow adapter for WeChat Mini Program subscription messages.
 */
final class WeChatMiniProgramSubscribeMessageSender implements WeChatSubscribeMessageSenderInterface
{
    public function __construct(
        private readonly SendWeChatMiniProgramSubscribeMessage $sendWeChatMiniProgramSubscribeMessage,
    ) {
    }

    public function sendSubscribeMessage(
        IntegrationConnection $connection,
        string $openid,
        string $templateId,
        array $data,
        ?string $page = null,
        string $miniprogramState = 'formal',
        string $lang = 'zh_CN',
        array $context = [],
    ): IntegrationOperationResult {
        $appId = trim((string) ($connection->getSettings()['app_id'] ?? ''));
        $appSecret = trim((string) ($connection->getCredentials()['miniprogram_app_secret'] ?? ''));

        try {
            ($this->sendWeChatMiniProgramSubscribeMessage)(
                $appId,
                $appSecret,
                $openid,
                $templateId,
                $data,
                $page,
                $miniprogramState,
                $lang,
            );
        } catch (WeChatSubscribeMessageException $exception) {
            return IntegrationOperationResult::failure($exception->getMessage());
        } catch (\Throwable $exception) {
            return IntegrationOperationResult::failure('WeChat subscribe message request failed.');
        }

        return IntegrationOperationResult::success('WeChat subscribe message sent.');
    }
}
