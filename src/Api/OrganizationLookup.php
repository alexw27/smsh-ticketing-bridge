<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use App\Organization\Domain\Entity\Organization;

interface OrganizationLookup
{
    public function findById(int $id): ?Organization;

    public function findBySlug(string $slug): ?Organization;

    public function findByCode(string $code): ?Organization;
}
