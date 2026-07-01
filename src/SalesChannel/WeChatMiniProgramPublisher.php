<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use App\Core\File\Service\FileStorageManager;
use App\Core\Routing\PublicUrlGenerator;
use App\Ticketing\Application\UpsertEventChannelAsset;
use App\Ticketing\Contract\SalesChannelPublisherInterface;
use App\Ticketing\Domain\Entity\Event;
use App\Ticketing\Domain\Entity\EventChannelPublication;
use App\Ticketing\Domain\Entity\SalesChannel;
use App\Ticketing\Domain\EventChannelAssetType;
use App\Ticketing\ValueObject\PublicationResult;

final class WeChatMiniProgramPublisher implements SalesChannelPublisherInterface
{
    private const SCANNER_PAGE = 'pages/scanner/scanner';

    public function __construct(
        private readonly ResolveWeChatMiniProgramCredentials $resolveWeChatMiniProgramCredentials,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
        private readonly WeChatMiniProgramQrCodeGenerator $weChatMiniProgramQrCodeGenerator,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly UpsertEventChannelAsset $upsertEventChannelAsset,
    ) {
    }

    public function supports(SalesChannel $channel): bool
    {
        return $channel->getCode() === SmshTicketingBridgeSalesChannel::CODE_WECHAT_SCANNER;
    }

    public function publish(Event $event, EventChannelPublication $publication): PublicationResult
    {
        $eventId = $event->getId();
        if ($eventId === null) {
            return PublicationResult::failure('Event must be persisted before channel publishing.');
        }

        $credentials = ($this->resolveWeChatMiniProgramCredentials)();
        if ($credentials === null) {
            return PublicationResult::failure(
                'WeChat scanner MiniProgram credentials are not configured. Enable the wechat-scanner integration with app_id and miniprogram_app_secret.',
            );
        }

        $page = self::SCANNER_PAGE;
        $scene = sprintf('id=%d', $eventId);

        try {
            $accessToken = $this->weChatMiniProgramAccessToken->fetch(
                $credentials['app_id'],
                $credentials['app_secret'],
            );
            $qrBinary = $this->weChatMiniProgramQrCodeGenerator->generate($accessToken, $scene, $page);
            $storedFile = $this->fileStorageManager->storeBinaryContent(
                $qrBinary,
                sprintf('event-%d-wechat-scanner-qr.png', $eventId),
                'sales-channel-assets/wechat-scanner',
                public: true,
                mimeType: 'image/png',
                extension: 'png',
            );
            $storedFileId = $storedFile->getId();
            if ($storedFileId === null) {
                return PublicationResult::failure('Stored QR code file has no identifier.');
            }

            $imageUrl = $this->publicUrlGenerator->generate('public_stored_file', ['id' => $storedFileId]);

            ($this->upsertEventChannelAsset)(
                $publication,
                EventChannelAssetType::QrCode,
                'Mini Program QR Code',
                $imageUrl,
                'image/png',
                [
                    'scene' => $scene,
                    'page' => $page,
                    'miniprogram_path' => sprintf('%s?id=%d', $page, $eventId),
                    'stored_file_id' => $storedFileId,
                    'credential_source' => $credentials['source'],
                ],
            );

            return PublicationResult::success();
        } catch (WeChatMiniProgramPublisherException $exception) {
            return PublicationResult::failure($exception->getMessage());
        }
    }
}
