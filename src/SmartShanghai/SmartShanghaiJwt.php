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
        [, $payload] = $this->decodeParts($jwt);

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

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function decodeParts(string $jwt): array
    {
        $jwt = trim($jwt);
        if ($jwt === '') {
            throw new \InvalidArgumentException('Missing SmartShanghai JWT.');
        }

        $segments = explode('.', $jwt);
        if (\count($segments) !== 3) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT.');
        }

        [$encodedHeader, $encodedPayload] = $segments;

        $headerJson = base64_decode($this->base64UrlToBase64($encodedHeader), true);
        $payloadJson = base64_decode($this->base64UrlToBase64($encodedPayload), true);
        if (!\is_string($headerJson) || !\is_string($payloadJson)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT encoding.');
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!\is_array($header) || !\is_array($payload)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT payload.');
        }

        /** @var array<string, mixed> $header */
        /** @var array<string, mixed> $payload */
        return [$header, $payload];
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
