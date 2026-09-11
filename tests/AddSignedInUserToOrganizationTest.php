<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Tests;

use App\Core\Auth\Entity\User;
use App\Organization\Domain\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Smsh\TicketingBridge\Api\AddSignedInUserToOrganization;
use Smsh\TicketingBridge\Api\JoinOrganizationApiPayload;
use Smsh\TicketingBridge\Api\OrganizationLookup;

final class AddSignedInUserToOrganizationTest extends TestCase
{
    public function testAddsUserToOrganizationById(): void
    {
        $organization = $this->organization('smartshanghai');
        $user = $this->user();
        $lookup = $this->createMock(OrganizationLookup::class);
        $lookup->expects(self::once())->method('findById')->with(12)->willReturn($organization);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $useCase = new AddSignedInUserToOrganization($lookup, $entityManager);
        $result = $useCase->handle($user, new JoinOrganizationApiPayload(organizationId: 12));

        self::assertFalse($result->alreadyMember);
        self::assertTrue($user->getOrganizations()->contains($organization));
    }

    public function testIsIdempotentWhenAlreadyAMember(): void
    {
        $organization = $this->organization('smartshanghai');
        $user = $this->user()->addOrganization($organization);
        $lookup = $this->createMock(OrganizationLookup::class);
        $lookup->expects(self::once())->method('findBySlug')->with('smartshanghai')->willReturn($organization);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $useCase = new AddSignedInUserToOrganization($lookup, $entityManager);
        $result = $useCase->handle($user, new JoinOrganizationApiPayload(slug: 'smartshanghai'));

        self::assertTrue($result->alreadyMember);
        self::assertCount(1, $user->getOrganizations());
    }

    public function testLooksUpByUppercasedCode(): void
    {
        $organization = $this->organization('smartshanghai')->setCode('SMSH');
        $lookup = $this->createMock(OrganizationLookup::class);
        $lookup->expects(self::once())->method('findByCode')->with('SMSH')->willReturn($organization);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $useCase = new AddSignedInUserToOrganization($lookup, $entityManager);
        $useCase->handle($this->user(), new JoinOrganizationApiPayload(code: 'smsh'));
    }

    public function testRejectsInactiveOrganization(): void
    {
        $organization = $this->organization('smartshanghai')->setIsActive(false);
        $lookup = $this->createStub(OrganizationLookup::class);
        $lookup->method('findById')->willReturn($organization);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $useCase = new AddSignedInUserToOrganization($lookup, $entityManager);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(AddSignedInUserToOrganization::ERROR_NOT_FOUND);

        $useCase->handle($this->user(), new JoinOrganizationApiPayload(organizationId: 12));
    }

    public function testRequiresAnIdentifier(): void
    {
        $useCase = new AddSignedInUserToOrganization(
            $this->createStub(OrganizationLookup::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(AddSignedInUserToOrganization::ERROR_IDENTIFIER_REQUIRED);

        $useCase->handle($this->user(), new JoinOrganizationApiPayload());
    }

    private function organization(string $slug): Organization
    {
        return (new Organization())
            ->setName('SmartShanghai')
            ->setSlug($slug);
    }

    private function user(): User
    {
        return (new User())
            ->setEmail('promoter@example.com')
            ->setPassword('not-used');
    }
}
