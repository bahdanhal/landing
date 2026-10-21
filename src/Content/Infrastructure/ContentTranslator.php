<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use App\Content\Application\SiteContent;
use App\Content\Domain\ContentSchema;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Applies page texts edited in the admin on top of the translation files. */
final readonly class ContentTranslator implements TranslatorInterface, LocaleAwareInterface
{
    public function __construct(
        private TranslatorInterface $inner,
        private SiteContent $content,
        private RequestStack $requests,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $request = $this->requests->getCurrentRequest();
        $locale ??= $this->getLocale();
        if ($request === null || !in_array($locale, ContentSchema::LOCALES, true) || ($domain !== null && $domain !== 'messages')) {
            return $this->inner->trans($id, $parameters, $domain, $locale);
        }
        $texts = $this->content->forRequest($request)['texts'] ?? null;
        $override = is_array($texts) && is_array($texts[$id] ?? null) ? ($texts[$id][$locale] ?? null) : null;
        if (is_string($override) && $override !== '') {
            return (new MessageFormatter())->format($override, $locale, $parameters);
        }

        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? $this->inner->getLocale();
    }

    public function setLocale(string $locale): void
    {
        if ($this->inner instanceof LocaleAwareInterface) {
            $this->inner->setLocale($locale);
        }
    }
}
