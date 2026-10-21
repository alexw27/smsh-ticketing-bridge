<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use App\Core\Audit\Service\AuditLoggerInterface;
use App\Core\Auth\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Smsh\TicketingBridge\WeChat\ExchangeWeChatPhoneNumberCode;
use Smsh\TicketingBridge\WeChat\ResolveWeChatMarketAccessToken;
use Smsh\TicketingBridge\WeChat\WeChatPhoneNumber;

/**
 * Exchanges a WeChat getPhoneNumber code and stores the verified phone on the user.
 */
final class ApplyWeChatMarketPhoneToUser
{
    public function __construct(
        private readonly ResolveWeChatMarketAccessToken $resolveWeChatMarketAccessToken,
        private readonly ExchangeWeChatPhoneNumberCode $exchangeWeChatPhoneNumberCode,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function handle(User $user, WeChatMarketPhoneApiPayload $payload): WeChatPhoneNumber
    {
        $accessToken = $this->resolveWeChatMarketAccessToken->resolve($payload->accessToken);
        $phone = $this->exchangeWeChatPhoneNumberCode->exchange($accessToken, $payload->code);

        $user
            ->setPhone($phone->phoneNumber)
            ->setPhoneVerifiedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->auditLogger->log('auth.phone_verified', [
            'category' => 'auth',
            'result' => 'success',
            'actorUser' => $user,
            'subject' => $user,
            'subjectType' => 'user',
            'subjectId' => (string) $user->getId(),
            'metadata' => [
                'source' => ResolveWeChatMarketAccessToken::CONNECTION_SLUG,
                'country_code' => $phone->countryCode !== '' ? $phone->countryCode : null,
                'phone_last4' => $this->lastFourDigits($phone->phoneNumber),
            ],
        ]);

        return $phone;
    }

    private function lastFourDigits(string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';
        if ($digits === '') {
            return null;
        }

        return substr($digits, -4);
    }
}
