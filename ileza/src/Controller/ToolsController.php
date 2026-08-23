<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ToolsController extends AbstractController
{
    #[Route(
        path: ['en' => '/salary-calculator', 'pl' => '/kalkulator-wynagrodzen'],
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
        path: ['en' => '/tools/poland-employment-calculator', 'pl' => '/pl/narzedzia/kalkulator-wynagrodzen'],
        name: 'legacy_income_calculator',
        methods: ['GET']
    )]
    public function legacyIncomeCalculator(Request $request): Response
    {
        return $this->redirectToRoute('income_calculator', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/tools', 'pl' => '/pl/narzedzia'],
        name: 'legacy_tools_home',
        methods: ['GET']
    )]
    public function legacyToolsHome(Request $request): Response
    {
        return $this->redirectToRoute('market_home', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/tools/polish-vat-calculator', 'pl' => '/pl/narzedzia/kalkulator-vat'],
        name: 'legacy_vat_calculator',
        methods: ['GET']
    )]
    public function legacyVatCalculator(Request $request): Response
    {
        return $this->redirectToRoute('income_calculator', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }
}
