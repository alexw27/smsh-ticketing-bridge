<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Ticketing\Contract\PromoterAffiliatePayloadExtensionInterface;
use App\Ticketing\Domain\Entity\EventAffiliate;

/**
 * Attaches the consumer WeChat Mini Program QR to promoter affiliate JSON.
 */
final class WeChatEventAffiliatePromoterPayloadExtension implements PromoterAffiliatePayloadExtensionInterface
{
    public function __construct(
        private readonly GenerateWeChatEventAffiliateMiniProgramQr $generateWeChatEventAffiliateMiniProgramQr,
    ) {
    }

    public function extraFields(EventAffiliate $affiliate): array
    {
        $view = $this->generateWeChatEventAffiliateMiniProgramQr->ensure($affiliate);

        return [
            'wechat_miniprogram_qr' => [
                'status' => $view->status,
                'image_url' => $view->imageUrl,
                'scene' => $view->scene,
                'page' => $view->page,
                'error_message' => $view->errorMessage,
                'reason' => $view->reason,
            ],
        ];
    }
}
