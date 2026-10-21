<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Core\Audit\Service\AuditLoggerInterface;
use App\Core\Auth\Entity\User;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Api\ApplyWeChatMarketPhoneToUser;
use Smsh\TicketingBridge\Api\WeChatMarketPhoneApiPayload;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\WeChat\ExchangeWeChatPhoneNumberCode;
use Smsh\TicketingBridge\WeChat\ResolveWeChatMarketAccessToken;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ApplyWeChatMarketPhoneToUserTest extends TestCase
{
    public function testStoresVerifiedPhoneAndWritesAuditEvent(): void
    {
        $user = (new User())->setEmail('buyer@example.com')->setPassword('not-used');
        $payload = new WeChatMarketPhoneApiPayload(code: 'wx-phone-code', accessToken: 'wx-token');

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('/wxa/business/getuserphonenumber', $url);
            self::assertSame(['code' => 'wx-phone-code'], json_decode((string) ($options['body'] ?? ''), true));

            return new MockResponse((string) json_encode([
                'errcode' => 0,
                'phone_info' => [
                    'phoneNumber' => '+8613812345678',
                    'purePhoneNumber' => '13812345678',
                    'countryCode' => '86',
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects(self::once())->method('log')->with(
            'auth.phone_verified',
            self::callback(static function (array $context) use ($user): bool {
                return ($context['category'] ?? null) === 'auth'
                    && ($context['actorUser'] ?? null) === $user
                    && ($context['metadata']['source'] ?? null) === 'wechat-market'
                    && ($context['metadata']['country_code'] ?? null) === '86'
                    && ($context['metadata']['phone_last4'] ?? null) === '5678';
            }),
        );

        $useCase = new ApplyWeChatMarketPhoneToUser(
            new ResolveWeChatMarketAccessToken(
                $this->createStub(IntegrationConnectionRepository::class),
                new WeChatMiniProgramAccessToken(new MockHttpClient()),
            ),
            new ExchangeWeChatPhoneNumberCode($httpClient),
            $entityManager,
            $auditLogger,
        );

        $result = $useCase->handle($user, $payload);

        self::assertSame('+8613812345678', $result->phoneNumber);
        self::assertSame('+8613812345678', $user->getPhone());
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getPhoneVerifiedAt());
    }
}
