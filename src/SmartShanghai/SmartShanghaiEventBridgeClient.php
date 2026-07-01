<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmartShanghaiEventBridgeClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws SmartShanghaiEventBridgeException
     */
    public function linkEvent(
        SmartShanghaiConnectionConfig $config,
        int $smtkEventId,
        string $accessToken,
    ): string {
        $apiToken = trim($config->apiToken);
        if ($apiToken === '') {
            throw new SmartShanghaiEventBridgeException(
                'SmartShanghai API key is not configured. Set API key on System → API / Integrations → SmartShanghai.',
            );
        }

        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new SmartShanghaiEventBridgeException('Report access token must not be empty.');
        }

        $response = $this->httpClient->request('PATCH', $this->buildUrl($config, $smtkEventId, $apiToken), [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'access_token' => $accessToken,
            ],
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $hint = $statusCode === 401
                ? ' Check the SmartShanghai integration API key (sent as ?key= on the request URL).'
                : '';

            throw new SmartShanghaiEventBridgeException(sprintf(
                'SmartShanghai event bridge returned HTTP %d: %s.%s',
                $statusCode,
                $this->responsePreview($response->getContent(false)),
                $hint,
            ));
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        if (!$this->isSuccessfulPayload($payload)) {
            $message = trim((string) ($payload['message'] ?? 'SmartShanghai event bridge request failed.'));

            throw new SmartShanghaiEventBridgeException($message !== '' ? $message : 'SmartShanghai event bridge request failed.');
        }

        $thumbnailPath = trim((string) ($payload['data']['thumbnail_path'] ?? ''));
        if ($thumbnailPath === '') {
            throw new SmartShanghaiEventBridgeException('SmartShanghai event bridge response did not include data.thumbnail_path.');
        }

        return $thumbnailPath;
    }

    private function buildUrl(SmartShanghaiConnectionConfig $config, int $smtkEventId, string $apiToken): string
    {
        $path = str_replace('{event_id}', (string) $smtkEventId, $config->eventBridgePath);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $config->apiBaseUrl . $path . '?' . http_build_query(['key' => $apiToken]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isSuccessfulPayload(array $payload): bool
    {
        if (($payload['is_successful'] ?? false) === true) {
            return true;
        }

        return ($payload['isSuccessful'] ?? false) === true;
    }

    private function responsePreview(string $responseBody): string
    {
        $responseBody = trim($responseBody);

        return mb_substr($responseBody !== '' ? $responseBody : '[empty response]', 0, 500);
    }
}
