<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Organization;

use App\Core\File\Entity\StoredFile;
use App\Core\File\Service\FileStorageManager;
use App\Core\Routing\PublicUrlGenerator;
use App\Organization\Domain\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SalesChannel\ResolveWeChatMiniProgramCredentials;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramAccessToken;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramPublisherException;
use Smsh\TicketingBridge\SalesChannel\WeChatMiniProgramQrCodeGenerator;

final class GenerateWeChatOrganizationMiniProgramQr
{
    public function __construct(
        private readonly ResolveWeChatMiniProgramCredentials $resolveWeChatMiniProgramCredentials,
        private readonly WeChatMiniProgramAccessToken $weChatMiniProgramAccessToken,
        private readonly WeChatMiniProgramQrCodeGenerator $weChatMiniProgramQrCodeGenerator,
        private readonly FileStorageManager $fileStorageManager,
        private readonly PublicUrlGenerator $publicUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ensure(Organization $organization): OrganizationWeChatQrPanelView
    {
        $organizationId = $organization->getId();
        if ($organizationId === null) {
            return OrganizationWeChatQrPanelView::notConfigured();
        }

        $scene = OrganizationWeChatQrScene::fromOrganizationId($organizationId);
        if ($scene === null) {
            return OrganizationWeChatQrPanelView::notConfigured();
        }

        $page = OrganizationWeChatQrPanelView::PAGE;
        $credentials = ($this->resolveWeChatMiniProgramCredentials)();
        if ($credentials === null) {
            return OrganizationWeChatQrPanelView::notConfigured('credentials');
        }

        $originalName = $this->originalName($organizationId, $scene, $page, $credentials['app_id']);
        $existing = $this->findStoredFile($originalName);
        if ($existing instanceof StoredFile) {
            return $this->readyView($existing, $scene, $page);
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
                'organization-assets/wechat-scanner',
                public: true,
                mimeType: 'image/png',
                extension: 'png',
            );

            return $this->readyView($storedFile, $scene, $page);
        } catch (WeChatMiniProgramPublisherException $exception) {
            $this->logger->error('ticketing.wechat_organization_qr.failed', [
                'organization_id' => $organizationId,
                'scene' => $scene,
                'page' => $page,
                'credential_source' => $credentials['source'],
                'message' => $exception->getMessage(),
            ]);

            return OrganizationWeChatQrPanelView::failed($exception->getMessage(), $scene, $page);
        } catch (\Throwable $exception) {
            $this->logger->error('ticketing.wechat_organization_qr.exception', [
                'organization_id' => $organizationId,
                'scene' => $scene,
                'page' => $page,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return OrganizationWeChatQrPanelView::failed($exception->getMessage(), $scene, $page);
        }
    }

    private function readyView(StoredFile $storedFile, string $scene, string $page): OrganizationWeChatQrPanelView
    {
        $storedFileId = $storedFile->getId();
        if ($storedFileId === null) {
            return OrganizationWeChatQrPanelView::failed('Stored QR code file has no identifier.', $scene, $page);
        }

        return OrganizationWeChatQrPanelView::ready(
            $this->publicUrlGenerator->generate('public_stored_file', ['id' => $storedFileId]),
            $scene,
            $page,
        );
    }

    private function originalName(int $organizationId, string $scene, string $page, string $appId): string
    {
        $fingerprint = substr(sha1($scene.'|'.$page.'|'.$appId), 0, 10);

        return sprintf('organization-%d-wechat-scanner-qr-%s.png', $organizationId, $fingerprint);
    }

    private function findStoredFile(string $originalName): ?StoredFile
    {
        $storedFile = $this->entityManager->getRepository(StoredFile::class)->findOneBy(
            ['originalName' => $originalName],
            ['id' => 'DESC'],
        );

        return $storedFile instanceof StoredFile ? $storedFile : null;
    }
}
