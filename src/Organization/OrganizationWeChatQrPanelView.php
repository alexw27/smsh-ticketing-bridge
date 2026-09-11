<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Organization;

final readonly class OrganizationWeChatQrPanelView
{
    public const STATUS_READY = 'ready';
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_FAILED = 'failed';

    public const PAGE = 'pages/org/org';

    public function __construct(
        public string $status,
        public ?string $imageUrl = null,
        public ?string $scene = null,
        public ?string $page = null,
        public ?string $errorMessage = null,
        public ?string $reason = null,
    ) {
    }

    public static function ready(string $imageUrl, string $scene, string $page): self
    {
        return new self(self::STATUS_READY, imageUrl: $imageUrl, scene: $scene, page: $page);
    }

    public static function notConfigured(?string $reason = null): self
    {
        return new self(self::STATUS_NOT_CONFIGURED, reason: $reason);
    }

    public static function failed(string $errorMessage, ?string $scene = null, ?string $page = null): self
    {
        return new self(self::STATUS_FAILED, scene: $scene, page: $page, errorMessage: $errorMessage);
    }
}
