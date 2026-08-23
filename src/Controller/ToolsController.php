<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ToolsController extends AbstractController
{
    #[Route(path: ['en' => '/', 'pl' => '/pl/'], name: 'landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('portfolio/home.html.twig');
    }

    #[Route(path: ['en' => '/tools', 'pl' => '/pl/narzedzia'], name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('toolbox/home.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/poland-employment-calculator', 'pl' => '/pl/narzedzia/kalkulator-wynagrodzen'],
        name: 'income_calculator',
        methods: ['GET']
    )]
    public function incomeCalculator(): Response
    {
        return $this->render('tools/income.html.twig', [
            'verified_at' => '2026-08-21',
        ]);
    }

    #[Route(
        path: ['en' => '/tools/bimi-studio', 'pl' => '/pl/narzedzia/bimi-studio'],
        name: 'bimi_studio',
        methods: ['GET']
    )]
    public function bimiStudio(): Response
    {
        return $this->render('tools/bimi.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/caddy-transpiler', 'pl' => '/pl/narzedzia/konwerter-caddyfile'],
        name: 'caddy_transpiler',
        methods: ['GET']
    )]
    public function caddyTranspiler(): Response
    {
        return $this->render('tools/caddy_transpiler.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/apple-pkpass-inspector', 'pl' => '/pl/narzedzia/inspektor-pkpass'],
        name: 'pkpass_inspector',
        methods: ['GET']
    )]
    public function pkpassInspector(): Response
    {
        return $this->render('tools/pkpass_inspector.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/polish-vat-calculator', 'pl' => '/pl/narzedzia/kalkulator-vat'],
        name: 'legacy_vat_calculator',
        methods: ['GET']
    )]
    public function legacyVatCalculator(Request $request): Response
    {
        return $this->redirectToRoute('income_calculator', ['_locale' => $request->getLocale()], 301);
    }
}
