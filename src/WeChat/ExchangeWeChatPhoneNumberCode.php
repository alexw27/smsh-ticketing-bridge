<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\WeChat;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Exchanges a getPhoneNumber dynamic token for the verified phone number.
 *
 * @see https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/getPhoneNumber.html
 */
final class ExchangeWeChatPhoneNumberCode
{
    private const PHONE_NUMBER_URL = 'https://api.weixin.qq.com/wxa/business/getuserphonenumber';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function exchange(string $accessToken, string $code): WeChatPhoneNumber
    {
        $accessToken = trim($accessToken);
        $code = trim($code);
        if ($accessToken === '') {
            throw new WeChatPhoneNumberExchangeException('WeChat MiniProgram access_token is required.');
        }
        if ($code === '') {
            throw new WeChatPhoneNumberExchangeException('WeChat getPhoneNumber code is required.');
        }

        try {
            $response = $this->httpClient->request('POST', self::PHONE_NUMBER_URL, [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'code' => $code,
                ],
                'timeout' => 10,
            ]);
            $rawBody = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('smsh.wechat.phone.transport_failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new WeChatPhoneNumberExchangeException(
                'Could not reach WeChat phone number API. Check outbound network access to api.weixin.qq.com.',
                previous: $exception,
            );
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->warning('smsh.wechat.phone.invalid_json');

            throw new WeChatPhoneNumberExchangeException(
                'WeChat phone number API returned invalid JSON.',
                previous: $exception,
            );
        }

        if (!\is_array($payload)) {
            throw new WeChatPhoneNumberExchangeException('WeChat phone number API returned invalid JSON.');
        }

        if (isset($payload['errcode']) && (int) $payload['errcode'] !== 0) {
            $errcode = (int) $payload['errcode'];
            $errmsg = trim((string) ($payload['errmsg'] ?? ''));

            $this->logger->warning('smsh.wechat.phone.exchange_failed', [
                'wechat_errcode' => $errcode,
                'wechat_errmsg' => $errmsg,
            ]);

            throw new WeChatPhoneNumberExchangeException(
                $this->resolveWeChatErrorMessage($errcode, $errmsg),
                wechatErrcode: $errcode,
            );
        }

        $info = $payload['phone_info'] ?? null;
        if (!\is_array($info)) {
            throw new WeChatPhoneNumberExchangeException('WeChat phone number API did not return phone_info.');
        }

        $phoneNumber = $this->resolvePhoneNumber($info);
        if ($phoneNumber === '') {
            throw new WeChatPhoneNumberExchangeException('WeChat phone number API did not return a phone number.');
        }

        return new WeChatPhoneNumber(
            phoneNumber: $phoneNumber,
            purePhoneNumber: trim((string) ($info['purePhoneNumber'] ?? '')),
            countryCode: trim((string) ($info['countryCode'] ?? '')),
        );
    }

    /**
     * @param array<string, mixed> $info
     */
    private function resolvePhoneNumber(array $info): string
    {
        $phoneNumber = trim((string) ($info['phoneNumber'] ?? ''));
        if ($phoneNumber !== '') {
            return $phoneNumber;
        }

        $countryCode = trim((string) ($info['countryCode'] ?? ''));
        $purePhoneNumber = trim((string) ($info['purePhoneNumber'] ?? ''));
        if ($countryCode !== '' && $purePhoneNumber !== '') {
            return '+' . $countryCode . $purePhoneNumber;
        }

        return $purePhoneNumber;
    }

    private function resolveWeChatErrorMessage(int $errcode, string $errmsg): string
    {
        $hint = match ($errcode) {
            40001, 40014, 42001 => 'The WeChat access_token is invalid or expired. Fetch a new MiniProgram token and retry.',
            40029, 40163 => 'The getPhoneNumber code is invalid, expired, or already used. Ask the user to authorize their phone number again.',
            default => '',
        };

        if ($hint !== '') {
            return $hint;
        }

        return $errmsg !== '' ? $errmsg : 'WeChat phone number exchange failed.';
    }
}
