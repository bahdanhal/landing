<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ToolsController extends AbstractController
{
    #[Route(path: ['en' => '/', 'pl' => '/pl/'], name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('toolbox/home.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/polish-vat-calculator', 'pl' => '/pl/narzedzia/kalkulator-vat'],
        name: 'vat_calculator',
        methods: ['GET'],
    )]
    public function vatCalculator(): Response
    {
        return $this->render('tools/vat.html.twig', [
            'verified_at' => '2026-08-21',
            'current_rates' => [23, 8, 5, 0],
            'future_estimate_date' => '2027-01-01',
        ]);
    }
}
