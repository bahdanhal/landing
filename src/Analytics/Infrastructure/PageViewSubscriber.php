<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\PageViewRepository;
use Bahdan\PrivacyAnalyticsBundle\EventSubscriber\PageViewSubscriber as BasePageViewSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class PageViewSubscriber implements EventSubscriberInterface
{
    private BasePageViewSubscriber $inner;

    public function __construct(
        PageViewRepository $pageViews,
        string $secret,
    ) {
        $this->inner = new BasePageViewSubscriber($pageViews, $secret);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $this->inner->onResponse($event);
    }
}
