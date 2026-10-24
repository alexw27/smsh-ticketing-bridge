<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiEmailMatchClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmartShanghaiEmailMatchClientTest extends TestCase
{
    public function testPostsEmailAndReturnsMatchFlag(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame(
                'https://www.smartshanghai.com/api2/ticketing/users/88421/email-matches',
                explode('?', $url, 2)[0],
            );
            self::assertTrue(
                ($options['query']['key'] ?? null) === 'partner-key'
                || str_contains($url, 'key=partner-key'),
            );
            $json = $options['json'] ?? null;
            if (!\is_array($json)) {
                $decoded = json_decode((string) ($options['body'] ?? ''), true);
                $json = \is_array($decoded) ? $decoded : null;
            }
            self::assertSame(['email' => 'buyer@example.test'], $json);

            return new MockResponse('{"match":true}');
        });

        $matches = (new SmartShanghaiEmailMatchClient($httpClient))->matches(
            self::config(),
            '88421',
            'Buyer@Example.TEST',
        );

        self::assertTrue($matches);
        self::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testAcceptsMatchesAlias(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"matches":false}'),
        ]);

        $matches = (new SmartShanghaiEmailMatchClient($httpClient))->matches(
            self::config(),
            '88421',
            'buyer@example.test',
        );

        self::assertFalse($matches);
    }

    private static function config(): SmartShanghaiConnectionConfig
    {
        return new SmartShanghaiConnectionConfig(
            loginUrl: 'https://www.smartshanghai.com/login',
            apiBaseUrl: 'https://www.smartshanghai.com',
            apiToken: 'partner-key',
            verifyUserPath: '/api2/ticketing/users/{user_id}',
        );
    }
}
