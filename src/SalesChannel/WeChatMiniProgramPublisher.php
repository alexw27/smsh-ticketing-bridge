<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\SalesChannel;

use App\Core\File\Service\FileStorageManager;
use App\Core\Integration\Entity\IntegrationConnection;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Core\Routing\PublicUrlGenerator;
use App\Ticketing\Application\UpsertEventChannelAsset;
use App\Ticketing\Contract\SalesChannelPublisherInterface;
use App\Ticketing\Domain\Entity\Event;
use App\Ticketing\Domain\Entity\EventChannelPublication;
use App\Ticketing\Domain\Entity\SalesChannel;
use App\Ticketing\Domain\EventChannelAssetType;
use App\Ticketing\ValueObject\PublicationResult;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class WeChatMiniProgramPublisher implements SalesChannelPublisherInterface
{
    private const DEFAULT_EVENT_PAGE = 'pages/event/event';

    public function __construct(
        private readonly ResolveWeChatMiniProgramCredentials $resolveWeChatMiniProgramCredentials,
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
        private readonly WeChatMiniProgramQrCodeGenerator $weChatMiniProgramQrCodeGenerator,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly UpsertEventChannelAsset $upsertEventChannelAsset,
    ) {
    }

    public function supports(SalesChannel $channel): bool
    {
        return $channel->getCode() === SalesChannel::CODE_WECHAT_MINIPROGRAM;
    }

    public function publish(Event $event, EventChannelPublication $publication): PublicationResult
    {
        $eventId = $event->getId();
        if ($eventId === null) {
            return PublicationResult::failure('Event must be persisted before channel publishing.');
        }

        $credentials = ($this->resolveWeChatMiniProgramCredentials)();
        if ($credentials === null) {
            return PublicationResult::failure('WeChat MiniProgram credentials are not configured.');
        }

        $page = $this->resolveEventPagePath();
        $scene = (string) $eventId;

        try {
            $accessToken = $this->weChatMiniProgramAccessToken->fetch(
                $credentials['app_id'],
                $credentials['app_secret'],
            );
            $qrBinary = $this->weChatMiniProgramQrCodeGenerator->generate($accessToken, $scene, $page);
            $storedFile = $this->fileStorageManager->storeBinaryContent(
                $qrBinary,
                sprintf('event-%d-wechat-miniprogram-qr.png', $eventId),
                'sales-channel-assets/wechat-miniprogram',
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
                    'stored_file_id' => $storedFileId,
                    'credential_source' => $credentials['source'],
                ],
            );

            ($this->upsertEventChannelAsset)(
                $publication,
                EventChannelAssetType::DeepLink,
                'Mini Program deep link',
                sprintf('%s?id=%d', $page, $eventId),
                metadata: [
                    'scene' => $scene,
                    'page' => $page,
                ],
            );

            return PublicationResult::success();
        } catch (WeChatMiniProgramPublisherException $exception) {
            return PublicationResult::failure($exception->getMessage());
        }
    }

    private function resolveEventPagePath(): string
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY) as $connection) {
            if (!$connection instanceof IntegrationConnection) {
                continue;
            }

            $page = SmartShanghaiConnectionConfig::fromConnection($connection)->miniprogramEventPage;
            if ($page !== '') {
                return $page;
            }
        }

        return self::DEFAULT_EVENT_PAGE;
    }
}
