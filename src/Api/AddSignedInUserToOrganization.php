<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use App\Core\Auth\Entity\User;
use App\Organization\Domain\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adds the authenticated user to an organization (SmartShanghai promoter/scanner flow).
 */
final class AddSignedInUserToOrganization
{
    public const ERROR_IDENTIFIER_REQUIRED = 'organization.identifier_required';
    public const ERROR_NOT_FOUND = 'organization.not_found';

    public function __construct(
        private readonly OrganizationLookup $organizationLookup,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(User $user, JoinOrganizationApiPayload $payload): JoinOrganizationResult
    {
        if (!$payload->hasIdentifier()) {
            throw new \DomainException(self::ERROR_IDENTIFIER_REQUIRED);
        }

        $organization = $this->findOrganization($payload);
        if (!$organization instanceof Organization || !$organization->isActive()) {
            throw new \DomainException(self::ERROR_NOT_FOUND);
        }

        $alreadyMember = $user->getOrganizations()->contains($organization);
        if (!$alreadyMember) {
            $user->addOrganization($organization);
            $this->entityManager->flush();
        }

        return new JoinOrganizationResult($organization, $alreadyMember);
    }

    private function findOrganization(JoinOrganizationApiPayload $payload): ?Organization
    {
        if ($payload->organizationId !== null) {
            return $this->organizationLookup->findById($payload->organizationId);
        }

        if ($payload->slug !== null) {
            return $this->organizationLookup->findBySlug($payload->slug);
        }

        if ($payload->code !== null) {
            return $this->organizationLookup->findByCode(strtoupper($payload->code));
        }

        return null;
    }
}
