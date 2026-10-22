<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\SmartShanghai\SendSmartShanghaiTicketingCancellation;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiTicketingCancellationException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SendSmartShanghaiTicketingCancellationTest extends TestCase
{
    public function testPostsCancellationMessageForTheSmartShanghaiUser(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame(
                'https://www.smartshanghai.com/api2/messages/88421/sendTicketingCancellation?key=partner-key',
                $url,
            );
            self::assertSame([
                'message' => 'This performance was cancelled.',
                'orderNumber' => 'A-100',
                'orderUrl' => 'https://api.smartticket.cn/orders/access/token',
                'orderId' => 1126264,
            ], self::requestJson($options));

            return new MockResponse('{"is_successful":true}');
        });

        (new SendSmartShanghaiTicketingCancellation($httpClient))(
            self::config(),
            '88421',
            'This performance was cancelled.',
            'A-100',
            'https://api.smartticket.cn/orders/access/token',
            1126264,
        );

        self::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testTreatsExplicitFailureFlagAsAnError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"isSuccessful":false,"message":"user not found"}', ['http_code' => 200]),
        ]);

        $this->expectException(SmartShanghaiTicketingCancellationException::class);
        $this->expectExceptionMessage('user not found');

        (new SendSmartShanghaiTicketingCancellation($httpClient))(
            self::config(),
            '88421',
            'Cancelled',
            'A-100',
            'https://api.smartticket.cn/orders/access/token',
            1126264,
        );
    }

    public function testRejectsNonSuccessHttpStatus(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('nope', ['http_code' => 401]),
        ]);

        $this->expectException(SmartShanghaiTicketingCancellationException::class);
        $this->expectExceptionMessage('HTTP 401');

        (new SendSmartShanghaiTicketingCancellation($httpClient))(
            self::config(),
            '88421',
            'Cancelled',
            'A-100',
            'https://api.smartticket.cn/orders/access/token',
            1126264,
        );
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

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function requestJson(array $options): array
    {
        if (is_array($options['json'] ?? null)) {
            return $options['json'];
        }

        $decoded = json_decode((string) ($options['body'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}
