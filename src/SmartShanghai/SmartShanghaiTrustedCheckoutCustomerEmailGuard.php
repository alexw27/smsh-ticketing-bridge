<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use App\Core\Auth\Entity\User;
use App\Core\Auth\Entity\UserExternalIdentity;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Ticketing\Contract\TrustedCheckoutCustomerEmailGuardInterface;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

/**
 * Re-checks a submitted checkout email against SmartShanghai via a boolean
 * email-match endpoint (API key only — no partner JWT required from clients).
 */
final class SmartShanghaiTrustedCheckoutCustomerEmailGuard implements TrustedCheckoutCustomerEmailGuardInterface
{
    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly SmartShanghaiEmailMatchClient $emailMatchClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validate(User $user, string $submittedEmail, ?string $providerAccessToken = null): ?array
    {
        unset($providerAccessToken);

        $identity = $this->smartShanghaiIdentity($user);
        if ($identity === null) {
            return null;
        }

        $smartShanghaiUserId = trim($identity->getProviderUserId());
        if ($smartShanghaiUserId === '') {
            return null;
        }

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->logger->warning('ticketing.smartshanghai_checkout_email.skipped_not_configured', [
                'user_id' => $user->getId(),
                'smartshanghai_user_id' => $smartShanghaiUserId,
            ]);

            return [
                'ok' => false,
                'code' => 'customer_email_verification_unavailable',
                'message' => 'SmartShanghai email verification is not configured.',
            ];
        }

        try {
            $matches = $this->emailMatchClient->matches($config, $smartShanghaiUserId, $submittedEmail);
        } catch (\Throwable $exception) {
            $this->logger->error('ticketing.smartshanghai_checkout_email.verify_failed', [
                'user_id' => $user->getId(),
                'smartshanghai_user_id' => $smartShanghaiUserId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'code' => 'customer_email_verification_failed',
                'message' => 'Could not verify the customer email with SmartShanghai.',
            ];
        }

        if (!$matches) {
            return [
                'ok' => false,
                'code' => 'customer_email_mismatch',
                'message' => 'The submitted email does not match your SmartShanghai account.',
            ];
        }

        return [
            'ok' => true,
            'email' => mb_strtolower(trim($submittedEmail)),
        ];
    }

    private function smartShanghaiIdentity(User $user): ?UserExternalIdentity
    {
        foreach ($user->getExternalIdentities() as $identity) {
            if (!$identity instanceof UserExternalIdentity) {
                continue;
            }
            if ($identity->getProviderKey() === SmartShanghaiProviderKey::KEY) {
                return $identity;
            }
        }

        return null;
    }

    private function resolveConfig(): ?SmartShanghaiConnectionConfig
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY) as $connection) {
            $config = SmartShanghaiConnectionConfig::fromConnection($connection);
            if ($config->isConfigured()) {
                return $config;
            }
        }

        return null;
    }
}
