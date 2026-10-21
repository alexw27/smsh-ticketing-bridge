<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\WeChat\ExchangeWeChatPhoneNumberCode;
use Smsh\TicketingBridge\WeChat\WeChatPhoneNumberExchangeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExchangeWeChatPhoneNumberCodeTest extends TestCase
{
    public function testExchangesCodeForPhoneNumber(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=wx-token', $url);
            self::assertSame(['code' => 'wx-phone-code'], json_decode((string) ($options['body'] ?? ''), true));

            return new MockResponse((string) json_encode([
                'errcode' => 0,
                'errmsg' => 'ok',
                'phone_info' => [
                    'phoneNumber' => '+8613812345678',
                    'purePhoneNumber' => '13812345678',
                    'countryCode' => 86,
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $phone = (new ExchangeWeChatPhoneNumberCode($httpClient))->exchange('wx-token', 'wx-phone-code');

        self::assertSame('+8613812345678', $phone->phoneNumber);
        self::assertSame('13812345678', $phone->purePhoneNumber);
        self::assertSame('86', $phone->countryCode);
    }

    public function testMapsWeChatErrorCodes(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode([
                'errcode' => 40029,
                'errmsg' => 'invalid code',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(WeChatPhoneNumberExchangeException::class);
        $this->expectExceptionMessage('The getPhoneNumber code is invalid, expired, or already used');

        try {
            (new ExchangeWeChatPhoneNumberCode($httpClient))->exchange('wx-token', 'used-code');
        } catch (WeChatPhoneNumberExchangeException $exception) {
            self::assertSame(40029, $exception->getWechatErrcode());
            self::assertSame(['wechat_errcode' => 40029], $exception->getDetails());

            throw $exception;
        }
    }
}
