<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

final class SmartShanghaiJwt
{
    /**
     * @return array<string, mixed>
     */
    public function payload(string $jwt): array
    {
        $jwt = trim($jwt);
        if ($jwt === '') {
            throw new \InvalidArgumentException('Missing SmartShanghai JWT.');
        }

        $segments = explode('.', $jwt);
        if (\count($segments) < 2) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT.');
        }

        $payloadJson = base64_decode($this->base64UrlToBase64($segments[1]), true);
        if (!\is_string($payloadJson)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT payload.');
        }

        $payload = json_decode($payloadJson, true);
        if (!\is_array($payload)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT payload.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    public function userId(string $jwt): string
    {
        $payload = $this->payload($jwt);
        $userId = $payload['user_id'] ?? $payload['sub'] ?? null;
        if (!\is_scalar($userId)) {
            throw new \InvalidArgumentException('SmartShanghai JWT does not contain a user_id.');
        }

        $userId = trim((string) $userId);
        if ($userId === '') {
            throw new \InvalidArgumentException('SmartShanghai JWT does not contain a user_id.');
        }

        return $userId;
    }

    private function base64UrlToBase64(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = \strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return $value;
    }
}
