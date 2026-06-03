<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SmartShanghai;

final class SmartShanghaiJwt
{
    private const EXPECTED_ALGORITHM = 'RS256';

    /**
     * @return array<string, mixed>
     */
    public function payload(string $jwt, SmartShanghaiConnectionConfig $config): array
    {
        [$header, $payload, $signingInput, $signature] = $this->decodeParts($jwt);

        if (($header['alg'] ?? null) !== self::EXPECTED_ALGORITHM) {
            throw new \InvalidArgumentException('SmartShanghai JWT must use RS256.');
        }

        $publicKey = openssl_pkey_get_public($config->publicKey);
        if ($publicKey === false) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT public key.');
        }

        $verified = openssl_verify($signingInput, $signature, $publicKey, \OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT signature.');
        }

        $this->validateClaims($payload, $config);

        return $payload;
    }

    public function userId(string $jwt, SmartShanghaiConnectionConfig $config): string
    {
        $payload = $this->payload($jwt, $config);
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
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
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

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $headerJson = base64_decode($this->base64UrlToBase64($encodedHeader), true);
        $payloadJson = base64_decode($this->base64UrlToBase64($encodedPayload), true);
        $signature = base64_decode($this->base64UrlToBase64($encodedSignature), true);
        if (!\is_string($headerJson) || !\is_string($payloadJson) || !\is_string($signature)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT encoding.');
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!\is_array($header) || !\is_array($payload)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT payload.');
        }

        /** @var array<string, mixed> $header */
        /** @var array<string, mixed> $payload */
        return [$header, $payload, $encodedHeader . '.' . $encodedPayload, $signature];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateClaims(array $payload, SmartShanghaiConnectionConfig $config): void
    {
        if (($payload['iss'] ?? null) !== $config->issuer) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT issuer.');
        }

        if (!$this->audienceMatches($payload['aud'] ?? null, $config->audience)) {
            throw new \InvalidArgumentException('Invalid SmartShanghai JWT audience.');
        }

        $now = time();
        $expiresAt = $payload['exp'] ?? null;
        if (!\is_int($expiresAt) || $expiresAt < ($now - $config->clockToleranceSeconds)) {
            throw new \InvalidArgumentException('SmartShanghai JWT has expired.');
        }

        $notBefore = $payload['nbf'] ?? null;
        if (\is_int($notBefore) && $notBefore > ($now + $config->clockToleranceSeconds)) {
            throw new \InvalidArgumentException('SmartShanghai JWT is not valid yet.');
        }
    }

    private function audienceMatches(mixed $audience, string $expectedAudience): bool
    {
        if (\is_string($audience)) {
            return $audience === $expectedAudience;
        }

        if (\is_array($audience)) {
            return \in_array($expectedAudience, $audience, true);
        }

        return false;
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
