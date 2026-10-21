<?php

declare(strict_types=1);

namespace App\Content\Presentation\Twig;

use App\Content\Application\SiteContent;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SiteContentExtension extends AbstractExtension
{
    public function __construct(private readonly SiteContent $content, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_content', $this->collections(...)),
            new TwigFunction('site_content_preview', $this->isPreview(...)),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function collections(): array
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        return $this->content->localize($this->content->forRequest($request), $request->getLocale());
    }

    public function isPreview(): bool
    {
        $request = $this->requests->getCurrentRequest();
        if ($request === null) {
            return false;
        }
        $this->content->forRequest($request);

        return $request->attributes->get(SiteContent::PREVIEW_ATTRIBUTE) === true;
    }
}
