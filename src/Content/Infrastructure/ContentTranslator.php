<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ContentTranslator implements TranslatorInterface, LocaleAwareInterface
{
    public function __construct(
        private TranslatorInterface $inner,
        private ContentStore $store,
        private RequestStack $requests,
        private ContentAdminAccess $access,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $request = $this->requests->getCurrentRequest();
        $locale ??= $this->getLocale();
        if ($request === null || !in_array($locale, ['en', 'pl'], true) || ($domain !== null && $domain !== 'messages')) {
            return $this->inner->trans($id, $parameters, $domain, $locale);
        }
        $attribute = '_site_content_' . $locale;
        if (!$request->attributes->has($attribute)) {
            $state = $this->store->read($locale);
            $preview = $request->query->get('_content_preview') === '1' && $this->access->isAuthenticated($request);
            $request->attributes->set($attribute, $state[$preview ? 'draft' : 'published']);
            $request->attributes->set('_site_content_preview', $preview);
        }
        $overrides = $request->attributes->get($attribute);
        if (is_array($overrides) && isset($overrides[$id]) && is_string($overrides[$id])) {
            return (new MessageFormatter())->format($overrides[$id], $locale, $parameters);
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
