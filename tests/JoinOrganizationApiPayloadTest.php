<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Api\JoinOrganizationApiPayload;

final class JoinOrganizationApiPayloadTest extends TestCase
{
    public function testReadsCamelCaseOrganizationId(): void
    {
        $payload = JoinOrganizationApiPayload::fromRequestBody(['organizationId' => 12]);

        self::assertSame(12, $payload->organizationId);
        self::assertTrue($payload->hasIdentifier());
    }

    public function testReadsSnakeCaseOrganizationId(): void
    {
        $payload = JoinOrganizationApiPayload::fromRequestBody(['organization_id' => '7']);

        self::assertSame(7, $payload->organizationId);
    }

    public function testReadsSlugAndCode(): void
    {
        $payload = JoinOrganizationApiPayload::fromRequestBody([
            'slug' => ' smartshanghai ',
            'code' => 'SMSH',
        ]);

        self::assertSame('smartshanghai', $payload->slug);
        self::assertSame('SMSH', $payload->code);
    }

    public function testEmptyBodyHasNoIdentifier(): void
    {
        $payload = JoinOrganizationApiPayload::fromRequestBody([]);

        self::assertFalse($payload->hasIdentifier());
    }

    public function testRejectsInvalidOrganizationIdType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('organizationId');

        JoinOrganizationApiPayload::fromRequestBody(['organizationId' => 'abc']);
    }
}
