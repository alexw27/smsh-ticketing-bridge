<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Bootstrap;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class RunBridgeReferenceDataBootstrapOnCommandTerminate
{
    public function __construct(
        private readonly EnsureWeChatScannerSalesChannel $ensureWeChatScannerSalesChannel,
    ) {
    }

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onCommandTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand()?->getName() !== 'app:bootstrap-reference-data') {
            return;
        }

        if ($event->getExitCode() !== 0) {
            return;
        }

        $this->ensureWeChatScannerSalesChannel->run();
    }
}
