<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * MiniProgram client-credential token.
 *
 * Uses WeChat's stable token API and caches the result until shortly before
 * expiry. The legacy /cgi-bin/token endpoint is limited to about 2,000 calls
 * a day and each call invalidates the previous token.
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-access-token/api_getstableaccesstoken.html
 */
final class WeChatMiniProgramAccessToken
{
    private const TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/stable_token';

    /** Refresh before WeChat's expiry so a request never uses a token in its last minutes. */
    private const EXPIRY_BUFFER_SECONDS = 300;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    public function fetch(string $appId, string $appSecret): string
    {
        $appId = trim($appId);
        $appSecret = trim($appSecret);
        if ($appId === '' || $appSecret === '') {
            throw new WeChatMiniProgramPublisherException('WeChat MiniProgram App ID and App Secret are required.');
        }

        if (!$this->cache instanceof CacheInterface) {
            return $this->requestStableToken($appId, $appSecret)['access_token'];
        }

        try {
            return $this->cache->get($this->cacheKey($appId, $appSecret), function (ItemInterface $item) use ($appId, $appSecret): string {
                $issued = $this->requestStableToken($appId, $appSecret);
                $item->expiresAfter($issued['ttl']);

                return $issued['access_token'];
            });
        } catch (WeChatMiniProgramPublisherException $exception) {
            throw $exception;
        } catch (\Throwable) {
            return $this->requestStableToken($appId, $appSecret)['access_token'];
        }
    }

    /**
     * @return array{access_token: string, ttl: int}
     */
    private function requestStableToken(string $appId, string $appSecret): array
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'json' => [
                    'grant_type' => 'client_credential',
                    'appid' => $appId,
                    'secret' => $appSecret,
                    'force_refresh' => false,
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

        $errcode = isset($payload['errcode']) && is_numeric($payload['errcode']) ? (int) $payload['errcode'] : 0;
        $token = trim((string) ($payload['access_token'] ?? ''));
        if ($errcode !== 0 || $token === '') {
            $message = trim((string) ($payload['errmsg'] ?? 'WeChat token API did not return access_token.'));

            throw new WeChatMiniProgramPublisherException($message !== '' ? $message : 'WeChat token API did not return access_token.');
        }

        $expiresIn = isset($payload['expires_in']) && is_numeric($payload['expires_in']) ? (int) $payload['expires_in'] : 7200;

        return [
            'access_token' => $token,
            'ttl' => $this->cacheTtl($expiresIn),
        ];
    }

    private function cacheTtl(int $expiresIn): int
    {
        if ($expiresIn <= 0) {
            return 60;
        }

        $ttl = $expiresIn - self::EXPIRY_BUFFER_SECONDS;
        if ($ttl >= 60) {
            return $ttl;
        }

        return max(1, $expiresIn - 15);
    }

    private function cacheKey(string $appId, string $appSecret): string
    {
        return 'wechat.miniprogram.access_token.'.hash('sha256', $appId."\0".$appSecret);
    }
}
