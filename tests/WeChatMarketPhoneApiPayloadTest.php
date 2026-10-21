<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Api\WeChatMarketPhoneApiPayload;

final class WeChatMarketPhoneApiPayloadTest extends TestCase
{
    public function testReadsCodeAndSnakeCaseAccessToken(): void
    {
        $payload = WeChatMarketPhoneApiPayload::fromRequestBody([
            'code' => '  wx-phone-code  ',
            'access_token' => '  wx-access  ',
        ]);

        self::assertSame('wx-phone-code', $payload->code);
        self::assertSame('wx-access', $payload->accessToken);
    }

    public function testReadsCamelCaseAccessToken(): void
    {
        $payload = WeChatMarketPhoneApiPayload::fromRequestBody([
            'code' => 'wx-phone-code',
            'accessToken' => 'wx-access',
        ]);

        self::assertSame('wx-access', $payload->accessToken);
    }

    public function testAllowsMissingAccessToken(): void
    {
        $payload = WeChatMarketPhoneApiPayload::fromRequestBody(['code' => 'wx-phone-code']);

        self::assertSame('wx-phone-code', $payload->code);
        self::assertNull($payload->accessToken);
    }

    public function testRejectsMissingCode(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(WeChatMarketPhoneApiPayload::ERROR_CODE_REQUIRED);

        WeChatMarketPhoneApiPayload::fromRequestBody([]);
    }

    public function testRejectsNonStringCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('code');

        WeChatMarketPhoneApiPayload::fromRequestBody(['code' => 12]);
    }
}
