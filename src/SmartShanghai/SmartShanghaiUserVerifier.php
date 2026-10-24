<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmartShanghaiUserVerifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{email?: string}
     */
    public function verify(SmartShanghaiConnectionConfig $config, string $userId, ?string $jwt = null): array
    {
        $url = $this->buildVerifyUrl($config, $userId);
        $headers = [
            'Accept' => 'application/json',
        ];
        // Login sends the partner JWT. Checkout re-checks use the partner API key only
        // (same pattern as ticketing cancellation): the ticketing JWT already identifies the user.
        $jwt = $jwt !== null ? trim($jwt) : '';
        if ($jwt !== '') {
            $headers['Authorization'] = 'Bearer ' . $jwt;
        }

        $response = $this->httpClient->request('GET', $url, [
            'query' => [
                'key' => $config->apiToken,
            ],
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'SmartShanghai rejected external user "%s" with HTTP %d: %s',
                $userId,
                $statusCode,
                $this->responsePreview($response->getContent(false)),
            ));
        }

        $payload = $response->toArray(false);
        $valid = $payload['valid'] ?? true;
        if ($valid !== true) {
            throw new \RuntimeException(sprintf(
                'SmartShanghai rejected external user "%s": %s',
                $userId,
                $this->responsePreview(json_encode($payload, \JSON_THROW_ON_ERROR)),
            ));
        }

        $email = $payload['email'] ?? null;

        return \is_string($email) && trim($email) !== ''
            ? ['email' => mb_strtolower(trim($email))]
            : [];
    }

    private function buildVerifyUrl(SmartShanghaiConnectionConfig $config, string $userId): string
    {
        $path = str_replace('{user_id}', rawurlencode($userId), $config->verifyUserPath);
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
