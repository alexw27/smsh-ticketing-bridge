<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\EventPublish;

use App\Core\File\Service\FileStorageManager;
use App\Core\Integration\Repository\IntegrationConnectionRepository;
use App\Ticketing\Application\CreateEventReportAccessToken;
use App\Ticketing\Contract\EventPublishHookInterface;
use App\Ticketing\Domain\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiConnectionConfig;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiEventBridgeClient;
use Smsh\TicketingBridge\SmartShanghai\SmartShanghaiEventBridgeException;
use Smsh\TicketingBridge\SmartShanghaiProviderKey;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmartShanghaiEventPublishHook implements EventPublishHookInterface
{
    private const REPORT_ACCESS_LABEL = 'SmartShanghai listing sync';

    public function __construct(
        private readonly IntegrationConnectionRepository $integrationConnectionRepository,
        private readonly CreateEventReportAccessToken $createEventReportAccessToken,
        private readonly SmartShanghaiEventBridgeClient $smartShanghaiEventBridgeClient,
        private readonly HttpClientInterface $httpClient,
        private readonly FileStorageManager $fileStorageManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onEventPublished(Event $event): void
    {
        $legacyId = $event->getLegacyId();
        if ($legacyId === null || $legacyId <= 0) {
            $this->logger->info('ticketing.smartshanghai_event_bridge.skipped_no_legacy_id', [
                'event_id' => $event->getId(),
            ]);

            return;
        }

        $config = $this->resolveConnectionConfig();
        if ($config === null) {
            $this->logger->warning('ticketing.smartshanghai_event_bridge.skipped_not_configured', [
                'event_id' => $event->getId(),
                'legacy_id' => $legacyId,
            ]);

            return;
        }

        $tokenResult = ($this->createEventReportAccessToken)($event, self::REPORT_ACCESS_LABEL);

        try {
            $thumbnailUrl = $this->smartShanghaiEventBridgeClient->linkEvent(
                $config,
                $legacyId,
                $tokenResult['rawToken'],
            );
            $this->applyThumbnailFromUrl($event, $thumbnailUrl);
            $this->entityManager->flush();

            $this->logger->info('ticketing.smartshanghai_event_bridge.linked', [
                'event_id' => $event->getId(),
                'legacy_id' => $legacyId,
                'thumbnail_url' => $thumbnailUrl,
            ]);
        } catch (SmartShanghaiEventBridgeException $exception) {
            throw $exception;
        }
    }

    private function resolveConnectionConfig(): ?SmartShanghaiConnectionConfig
    {
        foreach ($this->integrationConnectionRepository->findEnabledByProvider(SmartShanghaiProviderKey::KEY) as $connection) {
            $config = SmartShanghaiConnectionConfig::fromConnection($connection);
            if ($config->isConfigured()) {
                return $config;
            }
        }

        return null;
    }

    /**
     * @throws SmartShanghaiEventBridgeException
     */
    private function applyThumbnailFromUrl(Event $event, string $thumbnailUrl): void
    {
        $thumbnailUrl = trim($thumbnailUrl);
        if ($thumbnailUrl === '') {
            throw new SmartShanghaiEventBridgeException('Thumbnail URL must not be empty.');
        }

        try {
            $response = $this->httpClient->request('GET', $thumbnailUrl, [
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SmartShanghaiEventBridgeException(sprintf(
                    'Could not download SmartShanghai event thumbnail (HTTP %d).',
                    $statusCode,
                ));
            }

            $content = $response->getContent(false);
        } catch (SmartShanghaiEventBridgeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new SmartShanghaiEventBridgeException(
                'Could not download SmartShanghai event thumbnail.',
                previous: $exception,
            );
        }

        if ($content === '') {
            throw new SmartShanghaiEventBridgeException('SmartShanghai event thumbnail download returned empty content.');
        }

        $extension = $this->guessExtensionFromUrl($thumbnailUrl);
        $eventId = $event->getId();
        $storedFile = $this->fileStorageManager->storeBinaryContent(
            $content,
            sprintf('event-%d-smsh-thumbnail.%s', $eventId ?? 0, $extension ?? 'jpg'),
            'events',
            public: true,
            mimeType: null,
            extension: $extension,
        );

        $event->setThumbnailFile($storedFile);
        $this->entityManager->persist($event);
    }

    private function guessExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, \PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        return match ($extension) {
            'jpeg' => 'jpg',
            default => $extension,
        };
    }
}
