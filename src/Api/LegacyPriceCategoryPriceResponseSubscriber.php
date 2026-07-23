<?php

declare(strict_types=1);

namespace Smsh\TicketingBridge\Api;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * TEMPORARY SmartShanghai backward-compatibility shim.
 *
 * Core moved the pricing fields (`price`, `price_before_discount`, `customers_per_ticket`)
 * off {@see PriceCategory} and onto {@see EventDate}. Legacy SmartShanghai client apps still
 * read those fields from the embedded `price_category` object, so until they are updated we
 * mirror the values back onto every `price_category` node in `/api/v1` JSON responses.
 *
 * This lives in the SmartShanghai bridge (installed only on the SmartShanghai deployment) so
 * the compatibility behaviour is scoped to SmartShanghai and the core API contract stays clean.
 *
 * Remove this subscriber once the legacy clients read pricing from the event date payload.
 */
final class LegacyPriceCategoryPriceResponseSubscriber implements EventSubscriberInterface
{
    /**
     * Fields that historically lived on `price_category` and now live on the event date.
     */
    private const MIRRORED_KEYS = ['price', 'price_before_discount', 'customers_per_ticket'];

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        // Run late so we only touch the fully built response body.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -64]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        $response = $event->getResponse();
        if (!$response instanceof JsonResponse) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return;
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (!is_array($data)) {
            return;
        }

        $mutated = false;
        $this->mirrorInto($data, $mutated);

        if (!$mutated) {
            return;
        }

        // setData() re-encodes using this JsonResponse's own encoding options, preserving the
        // exact escaping (e.g. \uXXXX for CJK) that the original response used.
        $response->setData($data);
    }

    /**
     * Recursively walk the payload and, for any node embedding a `price_category`, copy the
     * pricing fields from the node's event-date data onto that `price_category`.
     *
     * Two shapes are covered:
     *  - schedule rows: pricing keys sit directly alongside `price_category`.
     *  - cart / checkout line items: pricing keys sit on the sibling `event_date` embed.
     *
     * @param array<array-key, mixed> $node
     */
    private function mirrorInto(array &$node, bool &$mutated): void
    {
        if (isset($node['price_category']) && is_array($node['price_category'])) {
            $source = $this->resolvePricingSource($node);
            if ($source !== null) {
                foreach (self::MIRRORED_KEYS as $key) {
                    if (array_key_exists($key, $source) && !array_key_exists($key, $node['price_category'])) {
                        $node['price_category'][$key] = $source[$key];
                        $mutated = true;
                    }
                }
            }
        }

        foreach ($node as $key => &$value) {
            // Never recurse into the price_category we may have just augmented.
            if ($key === 'price_category') {
                continue;
            }
            if (is_array($value)) {
                $this->mirrorInto($value, $mutated);
            }
        }
        unset($value);
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>|null
     */
    private function resolvePricingSource(array $node): ?array
    {
        // Schedule row: pricing keys are direct siblings of price_category.
        if (array_key_exists('price', $node)) {
            return $node;
        }

        // Cart / checkout line item: pricing keys live on the embedded event_date.
        if (isset($node['event_date']) && is_array($node['event_date']) && array_key_exists('price', $node['event_date'])) {
            return $node['event_date'];
        }

        return null;
    }
}
