<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendSmartShanghaiTicketingCancellation
{
    public const PATH = '/api2/messages/{user_id}/sendTicketingCancellation';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws SmartShanghaiTicketingCancellationException
     */
    public function __invoke(
        SmartShanghaiConnectionConfig $config,
        string $smartShanghaiUserId,
        string $message,
        string $orderNumber,
        string $orderUrl,
        int $orderId,
    ): void {
        $apiToken = trim($config->apiToken);
        if ($apiToken === '') {
            throw new SmartShanghaiTicketingCancellationException(
                'SmartShanghai API key is not configured. Set API key on System → API / Integrations → SmartShanghai.',
            );
        }

        $smartShanghaiUserId = trim($smartShanghaiUserId);
        if ($smartShanghaiUserId === '') {
            throw new SmartShanghaiTicketingCancellationException('SmartShanghai user id must not be empty.');
        }

        $apiBaseUrl = rtrim(trim($config->apiBaseUrl), '/');
        if ($apiBaseUrl === '') {
            throw new SmartShanghaiTicketingCancellationException('SmartShanghai API base URL is not configured.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl($apiBaseUrl, $smartShanghaiUserId, $apiToken), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => $message,
                    'orderNumber' => $orderNumber,
                    'orderUrl' => $orderUrl,
                    'orderId' => $orderId,
                ],
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (\Throwable $exception) {
            throw new SmartShanghaiTicketingCancellationException(
                'SmartShanghai ticketing cancellation request failed.',
                previous: $exception,
            );
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            $hint = $statusCode === 401
                ? ' Check the SmartShanghai integration API key (sent as ?key= on the request URL).'
                : '';

            throw new SmartShanghaiTicketingCancellationException(sprintf(
                'SmartShanghai ticketing cancellation returned HTTP %d: %s.%s',
                $statusCode,
                $this->responsePreview($body),
                $hint,
            ));
        }

        if ($this->isExplicitFailure($body)) {
            throw new SmartShanghaiTicketingCancellationException(sprintf(
                'SmartShanghai ticketing cancellation was rejected: %s.',
                $this->responsePreview($body),
            ));
        }
    }

    private function buildUrl(string $apiBaseUrl, string $smartShanghaiUserId, string $apiToken): string
    {
        $path = str_replace('{user_id}', rawurlencode($smartShanghaiUserId), self::PATH);

        return $apiBaseUrl . $path . '?' . http_build_query(['key' => $apiToken]);
    }

    private function isExplicitFailure(string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!is_array($payload)) {
            return false;
        }

        if (array_key_exists('is_successful', $payload) && $payload['is_successful'] === false) {
            return true;
        }

        return array_key_exists('isSuccessful', $payload) && $payload['isSuccessful'] === false;
    }

    private function responsePreview(string $responseBody): string
    {
        $responseBody = trim($responseBody);

        return mb_substr($responseBody !== '' ? $responseBody : '[empty response]', 0, 500);
    }
}
