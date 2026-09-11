<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Organization;

use App\Organization\Contract\OrganizationAdminFormExtensionInterface;
use App\Organization\Domain\Entity\Organization;

final class WeChatOrganizationAdminFormExtension implements OrganizationAdminFormExtensionInterface
{
    public function __construct(
        private readonly GenerateWeChatOrganizationMiniProgramQr $generateWeChatOrganizationMiniProgramQr,
    ) {
    }

    public function panels(Organization $organization, bool $isNew): array
    {
        if ($isNew) {
            return [];
        }

        return [[
            'template' => '@SmshTicketingBridge/admin/organizations/wechat_organization_qr_panel.html.twig',
            'context' => [
                'wechat_organization_qr' => $this->generateWeChatOrganizationMiniProgramQr->ensure($organization),
            ],
        ]];
    }
}
