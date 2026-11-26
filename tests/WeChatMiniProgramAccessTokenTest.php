<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WeChatMiniProgramAccessTokenTest extends TestCase
{
    public function testReusesStableTokenUntilItExpires(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$calls): MockResponse {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('https://api.weixin.qq.com/cgi-bin/stable_token', $url);
            self::assertStringNotContainsString('secret=', $url);
            $body = json_decode((string) ($options['body'] ?? ''), true);
            self::assertSame('client_credential', $body['grant_type'] ?? null);
            self::assertSame('wx-app', $body['appid'] ?? null);
            self::assertSame('app-secret', $body['secret'] ?? null);
            self::assertFalse($body['force_refresh'] ?? true);

            return new MockResponse((string) json_encode([
                'access_token' => 'stable-token',
                'expires_in' => 7200,
            ], JSON_THROW_ON_ERROR));
        });

        $tokens = new WeChatMiniProgramAccessToken($httpClient, new ArrayAdapter());

        self::assertSame('stable-token', $tokens->fetch('wx-app', 'app-secret'));
        self::assertSame('stable-token', $tokens->fetch('  wx-app  ', 'app-secret'));
        self::assertSame(1, $calls);
    }

    public function testDifferentCredentialsDoNotShareAToken(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['access_token' => 'token-a', 'expires_in' => 7200], JSON_THROW_ON_ERROR)),
            new MockResponse((string) json_encode(['access_token' => 'token-b', 'expires_in' => 7200], JSON_THROW_ON_ERROR)),
        ]);
        $tokens = new WeChatMiniProgramAccessToken($httpClient, new ArrayAdapter());

        self::assertSame('token-a', $tokens->fetch('wx-a', 'secret-a'));
        self::assertSame('token-b', $tokens->fetch('wx-b', 'secret-b'));
        self::assertSame(2, $httpClient->getRequestsCount());
    }

    public function testDoesNotCacheAWeChatError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['errcode' => 45009, 'errmsg' => 'reach max api daily quota limit'], JSON_THROW_ON_ERROR)),
            new MockResponse((string) json_encode(['access_token' => 'after-quota', 'expires_in' => 7200], JSON_THROW_ON_ERROR)),
        ]);
        $tokens = new WeChatMiniProgramAccessToken($httpClient, new ArrayAdapter());

        try {
            $tokens->fetch('wx-app', 'app-secret');
            self::fail('Expected WeChatMiniProgramPublisherException.');
        } catch (WeChatMiniProgramPublisherException $exception) {
            self::assertStringContainsString('quota', $exception->getMessage());
        }

        self::assertSame('after-quota', $tokens->fetch('wx-app', 'app-secret'));
        self::assertSame(2, $httpClient->getRequestsCount());
    }
}
