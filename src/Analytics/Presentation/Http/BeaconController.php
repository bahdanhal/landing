<?php

declare(strict_types=1);

namespace App\Analytics\Presentation\Http;

use App\Analytics\Domain\PageViewRepository;
use Bahdan\PrivacyAnalyticsBundle\EventSubscriber\PageViewSubscriber as BasePageViewSubscriber;
use Bahdan\PrivacyAnalyticsBundle\Presentation\Http\BeaconController as BaseBeaconController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class BeaconController
{
    private BaseBeaconController $inner;

    public function __construct(
        PageViewRepository $pageViews,
        string $secret,
    ) {
        $subscriber = new BasePageViewSubscriber($pageViews, $secret);
        $this->inner = new BaseBeaconController($pageViews, $secret, $subscriber);
    }

    #[Route('/api/pa/hit', name: 'privacy_analytics_beacon', methods: ['POST', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        return ($this->inner)($request);
    }
}
