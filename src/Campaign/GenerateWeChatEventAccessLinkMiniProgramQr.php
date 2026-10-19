<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Core\File\Entity\StoredFile;
use App\Core\File\Service\FileStorageManager;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Core\Routing\PublicUrlGenerator;
use App\Ticketing\Domain\Entity\EventAccessLink;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramQrCodeGenerator;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class GenerateWeChatEventAccessLinkMiniProgramQr
{
    public const DEFAULT_PAGE = 'pages/event/event';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly ResolveWeChatConsumerMiniProgramCredentials $resolveWeChatConsumerMiniProgramCredentials,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
        private readonly WeChatMiniProgramQrCodeGenerator $weChatMiniProgramQrCodeGenerator,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ensure(EventAccessLink $accessLink): CampaignWeChatQrPanelView
    {
        $accessLinkId = $accessLink->getId();
        $eventId = $accessLink->getEvent()?->getId();
        if ($accessLinkId === null || $eventId === null) {
            return CampaignWeChatQrPanelView::notConfigured();
        }

        $scene = EventAccessLinkWeChatQrScene::fromAccessLink($accessLink);
        if ($scene === null) {
            return CampaignWeChatQrPanelView::notConfigured('scene');
        }

        $smsConfig = $this->resolveSmartShanghaiConfig();
        $page = $this->resolvePage($smsConfig);

        $credentials = ($this->resolveWeChatConsumerMiniProgramCredentials)($smsConfig);
        if ($credentials === null) {
            return CampaignWeChatQrPanelView::notConfigured('credentials');
        }

        $originalName = $this->originalName($accessLinkId, $eventId, $scene, $page, $credentials['app_id']);
        $existing = $this->findStoredFile($originalName);
        if ($existing instanceof StoredFile) {
            return $this->readyView($existing, $scene, $page, $eventId);
        }

        try {
            $accessToken = $this->weChatMiniProgramAccessToken->fetch(
                $credentials['app_id'],
                $credentials['app_secret'],
            );
            $qrBinary = $this->weChatMiniProgramQrCodeGenerator->generate($accessToken, $scene, $page);
            $storedFile = $this->fileStorageManager->storeBinaryContent(
                $qrBinary,
                $originalName,
                'access-link-assets/wechat-miniprogram',
                public: true,
                mimeType: 'image/png',
                extension: 'png',
            );

            return $this->readyView($storedFile, $scene, $page, $eventId);
        } catch (WeChatMiniProgramPublisherException $exception) {
            $this->logger->error('ticketing.wechat_event_access_link_qr.failed', [
                'event_access_link_id' => $accessLinkId,
                'event_id' => $eventId,
                'scene' => $scene,
                'page' => $page,
                'credential_source' => $credentials['source'],
                'message' => $exception->getMessage(),
            ]);

            return CampaignWeChatQrPanelView::failed($exception->getMessage(), $scene, $page);
        } catch (\Throwable $exception) {
            $this->logger->error('ticketing.wechat_event_access_link_qr.exception', [
                'event_access_link_id' => $accessLinkId,
                'event_id' => $eventId,
                'scene' => $scene,
                'page' => $page,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return CampaignWeChatQrPanelView::failed($exception->getMessage(), $scene, $page);
        }
    }

    private function resolvePage(?SmartShanghaiConnectionConfig $smsConfig): string
    {
        $configured = $smsConfig?->miniprogramEventPage ?? '';
        if ($configured !== '') {
            return $configured;
        }

        return self::DEFAULT_PAGE;
    }

    private function readyView(StoredFile $storedFile, string $scene, string $page, int $eventId): CampaignWeChatQrPanelView
    {
        $storedFileId = $storedFile->getId();
        if ($storedFileId === null) {
            return CampaignWeChatQrPanelView::failed('Stored QR code file has no identifier.', $scene, $page);
        }

        $item = new CampaignWeChatQrItem(
            $this->publicUrlGenerator->generate('public_stored_file', ['id' => $storedFileId]),
            $scene,
            $page,
            $eventId,
        );

        return CampaignWeChatQrPanelView::fromGenerated([$item]);
    }

    private function originalName(int $accessLinkId, int $eventId, string $scene, string $page, string $appId): string
    {
        $fingerprint = substr(sha1($scene.'|'.$page.'|'.$appId), 0, 10);

        return sprintf('event-access-link-%d-event-%d-wechat-mp-qr-%s.png', $accessLinkId, $eventId, $fingerprint);
    }

    private function findStoredFile(string $originalName): ?StoredFile
    {
        $storedFile = $this->entityManager->getRepository(StoredFile::class)->findOneBy(
            ['originalName' => $originalName],
            ['id' => 'DESC'],
        );

        return $storedFile instanceof StoredFile ? $storedFile : null;
    }

    private function resolveSmartShanghaiConfig(): ?SmartShanghaiConnectionConfig
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY) as $connection) {
            $config = SmartShanghaiConnectionConfig::fromConnection($connection);
            if ($config->isConfigured()) {
                return $config;
            }
        }

        return null;
    }
}
