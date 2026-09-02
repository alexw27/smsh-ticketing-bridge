<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Campaign;

use App\Core\File\Entity\StoredFile;
use App\Core\File\Service\FileStorageManager;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Core\Routing\PublicUrlGenerator;
use App\Ticketing\Domain\Entity\Campaign;
use App\Ticketing\Domain\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramQrCodeGenerator;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;

final class GenerateWeChatCampaignMiniProgramQr
{
    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly ResolveWeChatConsumerMiniProgramCredentials $resolveWeChatConsumerMiniProgramCredentials,
        private readonly CampaignWeChatQrEventIds $campaignWeChatQrEventIds,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
        private readonly WeChatMiniProgramQrCodeGenerator $weChatMiniProgramQrCodeGenerator,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ensure(Campaign $campaign): CampaignWeChatQrPanelView
    {
        $campaignId = $campaign->getId();
        if ($campaignId === null) {
            return CampaignWeChatQrPanelView::notConfigured();
        }

        $eventIds = $this->campaignWeChatQrEventIds->fromCampaign($campaign);
        if ($eventIds === []) {
            return CampaignWeChatQrPanelView::notConfigured('event');
        }

        $smsConfig = $this->resolveSmartShanghaiConfig();
        $page = CampaignWeChatQrPanelView::DEFAULT_CAMPAIGN_PAGE;

        $credentials = ($this->resolveWeChatConsumerMiniProgramCredentials)($smsConfig);
        if ($credentials === null) {
            return CampaignWeChatQrPanelView::notConfigured('credentials');
        }

        $items = [];
        $lastError = null;
        $lastScene = null;
        foreach ($eventIds as $eventId) {
            $scene = CampaignWeChatQrScene::fromEventAndCampaign($eventId, $campaignId);
            if ($scene === null) {
                $lastError = 'WeChat MiniProgram QR scene exceeds the 32-character limit.';
                continue;
            }
            $lastScene = $scene;

            try {
                $items[] = $this->ensureForEvent($campaignId, $eventId, $scene, $page, $credentials);
            } catch (WeChatMiniProgramPublisherException $exception) {
                $lastError = $exception->getMessage();
                $this->logger->error('ticketing.wechat_campaign_qr.failed', [
                    'campaign_id' => $campaignId,
                    'event_id' => $eventId,
                    'scene' => $scene,
                    'page' => $page,
                    'credential_source' => $credentials['source'],
                    'message' => $exception->getMessage(),
                ]);
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
                $this->logger->error('ticketing.wechat_campaign_qr.exception', [
                    'campaign_id' => $campaignId,
                    'event_id' => $eventId,
                    'scene' => $scene,
                    'page' => $page,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($items !== []) {
            return CampaignWeChatQrPanelView::fromGenerated($items);
        }

        return CampaignWeChatQrPanelView::failed(
            $lastError ?? 'Could not generate the WeChat Mini Program QR.',
            $lastScene,
            $page,
        );
    }

    /**
     * @param array{app_id: string, app_secret: string, source: string} $credentials
     */
    private function ensureForEvent(
        int $campaignId,
        int $eventId,
        string $scene,
        string $page,
        array $credentials,
    ): CampaignWeChatQrItem {
        $originalName = $this->originalName($campaignId, $eventId, $scene, $page, $credentials['app_id']);
        $existing = $this->findStoredFile($originalName);
        if ($existing instanceof StoredFile) {
            return $this->itemFromStoredFile($existing, $scene, $page, $eventId);
        }

        $accessToken = $this->weChatMiniProgramAccessToken->fetch(
            $credentials['app_id'],
            $credentials['app_secret'],
        );
        $qrBinary = $this->weChatMiniProgramQrCodeGenerator->generate($accessToken, $scene, $page);
        $storedFile = $this->fileStorageManager->storeBinaryContent(
            $qrBinary,
            $originalName,
            'campaign-assets/wechat-miniprogram',
            public: true,
            mimeType: 'image/png',
            extension: 'png',
        );

        return $this->itemFromStoredFile($storedFile, $scene, $page, $eventId);
    }

    private function itemFromStoredFile(StoredFile $storedFile, string $scene, string $page, int $eventId): CampaignWeChatQrItem
    {
        $storedFileId = $storedFile->getId();
        if ($storedFileId === null) {
            throw new WeChatMiniProgramPublisherException('Stored QR code file has no identifier.');
        }

        $event = $this->entityManager->find(Event::class, $eventId);

        return new CampaignWeChatQrItem(
            $this->publicUrlGenerator->generate('public_stored_file', ['id' => $storedFileId]),
            $scene,
            $page,
            $eventId,
            $event?->getSlug() ?: null,
        );
    }

    private function originalName(int $campaignId, int $eventId, string $scene, string $page, string $appId): string
    {
        $fingerprint = substr(sha1($scene.'|'.$page.'|'.$appId), 0, 10);

        return sprintf('campaign-%d-event-%d-wechat-mp-qr-%s.png', $campaignId, $eventId, $fingerprint);
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
