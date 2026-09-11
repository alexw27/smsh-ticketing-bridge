<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use App\Organization\Domain\Entity\Organization;

final readonly class JoinOrganizationResult
{
    public function __construct(
        public Organization $organization,
        public bool $alreadyMember,
    ) {
    }
}
