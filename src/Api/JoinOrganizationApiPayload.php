<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

/**
 * Request body for POST /api/v1/me/organizations.
 *
 * Accepts camelCase and snake_case keys used by SmartShanghai clients.
 */
final class JoinOrganizationApiPayload
{
    public function __construct(
        public readonly ?int $organizationId = null,
        public readonly ?string $slug = null,
        public readonly ?string $code = null,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws \InvalidArgumentException when a provided field has an invalid JSON type
     */
    public static function fromRequestBody(array $body): self
    {
        return new self(
            organizationId: self::optionalPositiveInt($body, ['organizationId', 'organization_id']),
            slug: self::optionalTrimmedString($body, ['slug']),
            code: self::optionalTrimmedString($body, ['code']),
        );
    }

    public function hasIdentifier(): bool
    {
        return $this->organizationId !== null || $this->slug !== null || $this->code !== null;
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $keys
     */
    private static function optionalPositiveInt(array $body, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $body)) {
                continue;
            }

            $value = $body[$key];
            if (\is_int($value) && $value > 0) {
                return $value;
            }
            if (\is_string($value) && ctype_digit($value) && (int) $value > 0) {
                return (int) $value;
            }

            throw new \InvalidArgumentException($key);
        }

        return null;
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
