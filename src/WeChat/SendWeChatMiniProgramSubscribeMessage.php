<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * WeChat Mini Program subscription message send (订阅消息).
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_sendmessage.html
 */
final class SendWeChatMiniProgramSubscribeMessage
{
    public const SEND_URL = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly WeChatMiniProgramAccessToken $accessToken,
    ) {
    }

    /**
     * @param array<string, array{value: string}> $data
     *
     * @throws WeChatSubscribeMessageException
     */
    public function __invoke(
        string $appId,
        string $appSecret,
        string $openid,
        string $templateId,
        array $data,
        ?string $page = null,
        string $miniprogramState = 'formal',
        string $lang = 'zh_CN',
    ): void {
        $appId = trim($appId);
        $appSecret = trim($appSecret);
        $openid = trim($openid);
        $templateId = trim($templateId);
        if ($appId === '' || $appSecret === '') {
            throw new WeChatSubscribeMessageException('WeChat Mini Program App ID and App Secret are required.');
        }
        if ($openid === '' || $templateId === '' || $data === []) {
            throw new WeChatSubscribeMessageException('WeChat subscribe message requires an openid, template id, and keyword data.');
        }

        try {
            $token = $this->accessToken->fetch($appId, $appSecret);
        } catch (WeChatMiniProgramPublisherException $exception) {
            throw new WeChatSubscribeMessageException(
                $this->redact($exception->getMessage() !== '' ? $exception->getMessage() : 'Could not fetch a WeChat Mini Program access token.'),
                previous: $exception,
            );
        }

        $body = [
            'touser' => $openid,
            'template_id' => $templateId,
            'miniprogram_state' => $miniprogramState !== '' ? $miniprogramState : 'formal',
            'lang' => $lang !== '' ? $lang : 'zh_CN',
            'data' => $data,
        ];
        $page = $page !== null ? trim($page) : '';
        if ($page !== '') {
            $body['page'] = $page;
        }

        try {
            $response = $this->httpClient->request('POST', self::SEND_URL, [
                'query' => ['access_token' => $token],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $rawBody = $response->getContent(false);
        } catch (\Throwable $exception) {
            throw new WeChatSubscribeMessageException(
                'WeChat subscribe message request failed: ' . $this->redact($exception->getMessage()),
                previous: $exception,
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new WeChatSubscribeMessageException(sprintf(
                'WeChat subscribe message returned HTTP %d: %s.',
                $statusCode,
                $this->preview($rawBody),
            ));
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WeChatSubscribeMessageException('WeChat subscribe message returned invalid JSON.');
        }

        if (!is_array($payload)) {
            throw new WeChatSubscribeMessageException('WeChat subscribe message returned an unexpected payload.');
        }

        $errcode = (int) ($payload['errcode'] ?? 0);
        if ($errcode === 0) {
            return;
        }

        $errmsg = trim((string) ($payload['errmsg'] ?? ''));
        throw new WeChatSubscribeMessageException(sprintf(
            'WeChat subscribe message was rejected (%d)%s%s',
            $errcode,
            $errmsg !== '' ? ': ' . $errmsg : '.',
            $this->hint($errcode),
        ));
    }

    private function hint(int $errcode): string
    {
        return match ($errcode) {
            43101 => ' The user has not accepted this subscription template.',
            40037 => ' template_id is invalid.',
            47003 => ' A template field value does not match WeChat keyword rules.',
            41030 => ' The Mini Program page path is invalid.',
            default => '',
        };
    }

    private function preview(string $responseBody): string
    {
        $responseBody = trim($responseBody);

        return mb_substr($this->redact($responseBody !== '' ? $responseBody : '[empty response]'), 0, 500);
    }

    private function redact(string $message): string
    {
        return (string) preg_replace('/access_token=[^&\s]+/', 'access_token=redacted', $message);
    }
}
