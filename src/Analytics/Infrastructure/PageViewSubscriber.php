<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\PageViewRepository;
use Bahdan\PrivacyAnalyticsBundle\EventSubscriber\PageViewSubscriber as BasePageViewSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class PageViewSubscriber implements EventSubscriberInterface
{
    private const string AUTOMATION_PATTERN = '/googleother|google-inspectiontool|bahdantoolbox|cms-checker|crt-indexer'
        . '|iphone os 13_2_3 like mac os x|android 7\.0; sm-g892a/';
    private const string PROBE_PATH_PATTERN = '#(?:^|/)(?:wp-admin|wp-content|wp-includes)(?:/|$)'
        . '|(?:^|/)(?:\.env|\.git)(?:/|$)|\.php(?:/|$)#i';

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
        $request = $event->getRequest();
        $userAgent = strtolower(trim((string) $request->headers->get('User-Agent')));
        $requestUriPath = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);

        if (
            $userAgent === ''
            || preg_match(self::AUTOMATION_PATTERN, $userAgent) === 1
            || preg_match(self::PROBE_PATH_PATTERN, $requestUriPath) === 1
            || $this->hasSuspiciousChromiumHeaders($request, $userAgent)
        ) {
            return;
        }

        $this->inner->onResponse($event);
    }

    private function hasSuspiciousChromiumHeaders(Request $request, string $userAgent): bool
    {
        return preg_match('/(?:chrome|chromium|edg)\/([0-9]+)/', $userAgent, $matches) === 1
            && (int) $matches[1] >= 80
            && !$request->headers->has('Sec-Fetch-Mode')
            && !$request->headers->has('Sec-CH-UA');
    }
}
