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
        path: ['en' => '/tools/cidr-subnet-matrix', 'pl' => '/pl/narzedzia/matryca-cidr'],
        name: 'cidr_matrix',
        methods: ['GET', 'POST']
    )]
    public function cidrMatrix(Request $request, \App\CidrMatrix\Application\CidrMatrixService $cidrService): Response
    {
        $cidrsInput = (string) $request->request->get('cidrs', $request->query->get('cidrs', '10.0.0.0/16, 10.0.32.0/20'));
        $parentCidr = (string) $request->request->get('parent_cidr', $request->query->get('parent_cidr', ''));
        $requestedFreePrefixString = (string) $request->request->get('requested_free_prefix', $request->query->get('requested_free_prefix', ''));

        $cidrList = array_values(array_filter(array_map('trim', explode(',', str_replace(["\r\n", "\n", "\r"], ',', $cidrsInput)))));
        $requestedFreePrefix = ctype_digit($requestedFreePrefixString) ? (int) $requestedFreePrefixString : null;
        $parentCidrParam = $parentCidr !== '' ? $parentCidr : null;

        $result = $cidrService->analyze($cidrList, $requestedFreePrefix, $parentCidrParam);
        $presets = $cidrService->getPresets();

        return $this->render('tools/cidr_matrix.html.twig', [
            'raw_cidrs' => $cidrsInput,
            'parent_cidr' => $parentCidr,
            'requested_free_prefix' => $requestedFreePrefixString,
            'result' => $result,
            'presets' => $presets,
        ]);
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
