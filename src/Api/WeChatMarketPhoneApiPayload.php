<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

/**
 * Request body for POST /api/v1/auth/external/wechat-market/phone.
 *
 * Accepts camelCase and snake_case keys used by SmartShanghai clients.
 */
final class WeChatMarketPhoneApiPayload
{
    public const ERROR_CODE_REQUIRED = 'wechat_phone.code_required';

    public function __construct(
        public readonly string $code,
        public readonly ?string $accessToken = null,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws \InvalidArgumentException when a provided field has an invalid JSON type
     * @throws \DomainException when code is missing
     */
    public static function fromRequestBody(array $body): self
    {
        $code = self::optionalTrimmedString($body, ['code']);
        if ($code === null) {
            throw new \DomainException(self::ERROR_CODE_REQUIRED);
        }

        return new self(
            code: $code,
            accessToken: self::optionalTrimmedString($body, ['accessToken', 'access_token']),
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $keys
     */
    private static function optionalTrimmedString(array $body, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $body)) {
                continue;
            }

            $value = $body[$key];
            if (!\is_string($value)) {
                throw new \InvalidArgumentException($key);
            }

            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
