<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use App\Organization\Domain\Entity\Organization;
use App\Organization\Domain\Repository\OrganizationRepository;

final class DoctrineOrganizationLookup implements OrganizationLookup
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    public function findById(int $id): ?Organization
    {
        $organization = $this->organizationRepository->find($id);

        return $organization instanceof Organization ? $organization : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $organization = $this->organizationRepository->findOneBy(['slug' => $slug]);

        return $organization instanceof Organization ? $organization : null;
    }

    public function findByCode(string $code): ?Organization
    {
        $organization = $this->organizationRepository->findOneBy(['code' => $code]);

        return $organization instanceof Organization ? $organization : null;
    }
}
