<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\ExternalIdentity;

use App\Core\Auth\ExternalIdentity\ExternalIdentityAuthenticationHandlerInterface;
use App\Core\Auth\ExternalIdentity\ExternalIdentityProfile;
use App\Core\Auth\ExternalIdentity\ExternalIdentityProviderInterface;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiJwt;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiUserVerifier;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class SmartShanghaiExternalIdentityAuthenticationHandler implements ExternalIdentityAuthenticationHandlerInterface
{
    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SmartShanghaiJwt $jwt,
        private readonly SmartShanghaiUserVerifier $userVerifier,
    ) {
    }

    public function supports(string $providerKey): bool
    {
        return $providerKey === SmartShanghaiProviderKey::KEY;
    }

    public function start(ExternalIdentityProviderInterface $provider, Request $request): ?Response
    {
        $config = $this->configuredConnection();
        if ($config === null) {
            return null;
        }

        $callbackUrl = $this->urlGenerator->generate('app_auth_external_identity_check', [
            'providerKey' => $provider->getProviderKey(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($this->appendQuery($config->loginUrl, [
            'callback_url' => $callbackUrl,
        ]));
    }

    public function authenticate(ExternalIdentityProviderInterface $provider, Request $request): ExternalIdentityProfile
    {
        $config = $this->configuredConnection();
        if ($config === null) {
            throw new CustomUserMessageAuthenticationException('auth.external_identity.not_configured');
        }

        $token = $this->resolveJwt($request);
        try {
            $userId = $this->jwt->userId($token);
            $verified = $this->userVerifier->verify($config, $userId, $token);
        } catch (\InvalidArgumentException|\RuntimeException) {
            throw new CustomUserMessageAuthenticationException('auth.external_identity.unavailable_provider');
        }

        return new ExternalIdentityProfile(
            providerUserId: $userId,
            email: $verified['email'] ?? null,
        );
    }

    private function configuredConnection(): ?SmartShanghaiConnectionConfig
    {
        $connections = $this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY);
        foreach ($connections as $connection) {
            $config = SmartShanghaiConnectionConfig::fromConnection($connection);
            if ($config->isConfigured()) {
                return $config;
            }
        }

        return null;
    }

    private function resolveJwt(Request $request): string
    {
        $token = trim((string) ($request->query->get('jwt') ?? $request->query->get('token') ?? $request->request->get('jwt') ?? $request->request->get('token') ?? ''));
        if ($token !== '') {
            return $token;
        }

        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        throw new CustomUserMessageAuthenticationException('auth.external_identity.unavailable_provider');
    }

    /**
     * @param array<string, string> $query
     */
    private function appendQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }
}
