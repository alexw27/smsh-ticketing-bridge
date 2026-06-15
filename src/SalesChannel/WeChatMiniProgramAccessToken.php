<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeChatMiniProgramAccessToken
{
    private const TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(string $appId, string $appSecret): string
    {
        try {
            $response = $this->httpClient->request('GET', self::TOKEN_URL, [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid' => $appId,
                    'secret' => $appSecret,
                ],
                'timeout' => 10,
            ]);
            $rawBody = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            throw new WeChatMiniProgramPublisherException(
                'Could not reach WeChat token API. Check outbound network access to api.weixin.qq.com.',
                previous: $exception,
            );
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new WeChatMiniProgramPublisherException('WeChat token API returned invalid JSON.');
        }

        $token = trim((string) ($payload['access_token'] ?? ''));
        if ($token === '') {
            $message = trim((string) ($payload['errmsg'] ?? 'WeChat token API did not return access_token.'));

            throw new WeChatMiniProgramPublisherException($message);
        }

        return $token;
    }
}
