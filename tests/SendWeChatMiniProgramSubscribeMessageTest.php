<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\WeChat\SendWeChatMiniProgramSubscribeMessage;
use Smsh\TicketingBridge\WeChat\WeChatSubscribeMessageException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SendWeChatMiniProgramSubscribeMessageTest extends TestCase
{
    public function testPostsSubscribeMessageWithAClientCredentialToken(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            if (str_contains($url, '/cgi-bin/token')) {
                self::assertSame('GET', $method);

                return new MockResponse('{"access_token":"token-1","expires_in":7200}');
            }

            self::assertSame('POST', $method);
            self::assertStringContainsString('access_token=token-1', $url);
            $decoded = json_decode((string) ($options['body'] ?? ''), true);
            self::assertSame([
                'touser' => 'openid-1',
                'template_id' => 'tmpl-1',
                'miniprogram_state' => 'formal',
                'lang' => 'zh_CN',
                'data' => [
                    'thing1' => ['value' => 'Order A-100'],
                ],
                'page' => 'pages/order/detail?id=12',
            ], $decoded);

            return new MockResponse('{"errcode":0,"errmsg":"ok"}');
        });

        $send = new SendWeChatMiniProgramSubscribeMessage($httpClient, new WeChatMiniProgramAccessToken($httpClient));
        $send('wx-app', 'secret', 'openid-1', 'tmpl-1', [
            'thing1' => ['value' => 'Order A-100'],
        ], 'pages/order/detail?id=12');

        self::assertSame(2, $httpClient->getRequestsCount());
    }

    public function testExplainsWhenTheUserHasNotSubscribed(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"access_token":"token-1","expires_in":7200}'),
            new MockResponse('{"errcode":43101,"errmsg":"user refuse to accept the msg"}'),
        ]);

        $send = new SendWeChatMiniProgramSubscribeMessage($httpClient, new WeChatMiniProgramAccessToken($httpClient));

        $this->expectException(WeChatSubscribeMessageException::class);
        $this->expectExceptionMessage('43101');

        $send('wx-app', 'secret', 'openid-1', 'tmpl-1', [
            'thing1' => ['value' => 'Order A-100'],
        ]);
    }
}
