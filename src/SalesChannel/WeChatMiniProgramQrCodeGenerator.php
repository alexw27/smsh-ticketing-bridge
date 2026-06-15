<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeChatMiniProgramQrCodeGenerator
{
    private const QR_CODE_URL = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function generate(string $accessToken, string $scene, string $page): string
    {
        $scene = trim($scene);
        $page = trim($page);
        if ($scene === '') {
            throw new WeChatMiniProgramPublisherException('WeChat MiniProgram QR scene must not be empty.');
        }
        if ($page === '') {
            throw new WeChatMiniProgramPublisherException('WeChat MiniProgram QR page path must not be empty.');
        }

        try {
            $response = $this->httpClient->request('POST', self::QR_CODE_URL, [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'scene' => $scene,
                    'page' => $page,
                    'check_path' => false,
                    'width' => 430,
                ],
                'timeout' => 15,
            ]);
            $content = $response->getContent(false);
            $contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');
        } catch (TransportExceptionInterface $exception) {
            throw new WeChatMiniProgramPublisherException(
                'Could not reach WeChat QR code API.',
                previous: $exception,
            );
        }

        if (str_contains($contentType, 'application/json') || str_starts_with(trim($content), '{')) {
            /** @var array<string, mixed>|null $payload */
            $payload = json_decode($content, true);
            $message = is_array($payload)
                ? trim((string) ($payload['errmsg'] ?? 'WeChat QR code API returned an error.'))
                : 'WeChat QR code API returned an error.';

            throw new WeChatMiniProgramPublisherException($message);
        }

        if ($content === '') {
            throw new WeChatMiniProgramPublisherException('WeChat QR code API returned an empty response.');
        }

        return $content;
    }
}
