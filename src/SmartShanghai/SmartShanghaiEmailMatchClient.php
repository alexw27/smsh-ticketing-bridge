<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Partner API-key check: does this SmartShanghai user own this email?
 * Returns only a boolean — does not expose the stored email.
 */
final class SmartShanghaiEmailMatchClient
{
    public const DEFAULT_PATH = '/api2/ticketing/users/{user_id}/email-matches';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function matches(SmartShanghaiConnectionConfig $config, string $userId, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $url = $this->buildUrl($config, $userId);
        $response = $this->httpClient->request('POST', $url, [
            'query' => [
                'key' => $config->apiToken,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'email' => $email,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'SmartShanghai email match rejected for user "%s" with HTTP %d: %s',
                $userId,
                $statusCode,
                $this->responsePreview($response->getContent(false)),
            ));
        }

        $payload = $response->toArray(false);
        $match = $payload['match'] ?? $payload['matches'] ?? null;
        if (\is_bool($match)) {
            return $match;
        }

        throw new \RuntimeException(sprintf(
            'SmartShanghai email match returned an unexpected payload for user "%s": %s',
            $userId,
            $this->responsePreview(json_encode($payload, \JSON_THROW_ON_ERROR)),
        ));
    }

    private function buildUrl(SmartShanghaiConnectionConfig $config, string $userId): string
    {
        $path = $config->emailMatchPath !== ''
            ? $config->emailMatchPath
            : self::DEFAULT_PATH;
        $path = str_replace('{user_id}', rawurlencode($userId), $path);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $config->apiBaseUrl . $path;
    }

    private function responsePreview(string $responseBody): string
    {
        $responseBody = trim($responseBody);

        return mb_substr($responseBody !== '' ? $responseBody : '[empty response]', 0, 500);
    }
}
