<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

final readonly class CampaignWeChatQrPanelView
{
    public const STATUS_READY = 'ready';
    public const STATUS_NO_CODE = 'no_code';
    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_FAILED = 'failed';

    public const DEFAULT_EVENT_PAGE = 'pages/event/event';

    public const DEFAULT_CAMPAIGN_PAGE = 'pages/smtkEvent/smtkEvent';

    /**
     * @param list<CampaignWeChatQrItem> $items
     */
    public function __construct(
        public string $status,
        public ?string $imageUrl = null,
        public ?string $scene = null,
        public ?string $page = null,
        public ?string $errorMessage = null,
        public ?string $reason = null,
        public array $items = [],
    ) {
    }

    /**
     * @param list<CampaignWeChatQrItem> $items
     */
    public static function fromGenerated(array $items): self
    {
        $first = $items[0] ?? null;

        return new self(
            self::STATUS_READY,
            imageUrl: $first?->imageUrl,
            scene: $first?->scene,
            page: $first?->page,
            items: $items,
        );
    }

    public static function noCode(): self
    {
        return new self(self::STATUS_NO_CODE);
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
