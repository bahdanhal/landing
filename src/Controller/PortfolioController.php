<?php

declare(strict_types=1);

namespace App\Controller;

use App\Lead\Application\CaptureLead;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PortfolioController extends AbstractController
{
    public function __construct(
        private readonly CaptureLead $captureLead,
        private readonly RateLimiterFactory $contactLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: ['en' => '/', 'pl' => '/pl/'], name: 'landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('portfolio/home.html.twig');
    }

    #[Route(path: ['en' => '/tools', 'pl' => '/pl/narzedzia'], name: 'legacy_tools', methods: ['GET'])]
    public function legacyTools(Request $request): Response
    {
        return $this->redirectToRoute('landing', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: ['en' => '/contact', 'pl' => '/pl/kontakt'], name: 'contact', methods: ['POST'])]
    public function contact(Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('contact.invalid_origin')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('contact.saved')]);
        }

        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        $limit = $this->contactLimiter->create($limitKey)->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('contact.too_many')], 429);
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $phone = trim((string) $request->request->get('phone'));
        $message = trim((string) $request->request->get('message'));
        if (
            strlen($email) > 254
            || mb_strlen($phone) > 30
            || mb_strlen($message) > 1000
            || ($email === '' && $phone === '')
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            || ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,30}$/', $phone))
        ) {
            return $this->json(['error' => $this->translator->trans('contact.invalid_contact')], 422);
        }

        try {
            $this->captureLead->execute(
                $email,
                $phone,
                $message,
                $request->getClientIp() ?? 'unknown',
                (string) $request->request->get('source', 'website'),
            );
        } catch (\Throwable) {
            return $this->json(['error' => $this->translator->trans('contact.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('contact.saved')]);
    }
}
