<?php

declare(strict_types=1);

namespace App\Income\Domain;

use App\Shared\Domain\Grosz;

final readonly class PolishIncomeCalculator
{
    /**
     * @param array{
     *     budget: float|int,
     *     zus?: string,
     *     taxation?: string,
     *     lumpRate?: float|int,
     *     costs?: float|int,
     *     studentUnder26?: bool
     * } $options
     * @return array<string, array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}>
     */
    public function compare(array $options): array
    {
        $budget = max(0.0, (float) ($options['budget']));
        $studentUnder26 = (bool) ($options['studentUnder26'] ?? false);

        return [
            'employment' => $this->employment($budget),
            'mandate' => $this->mandate($budget, $studentUnder26),
            'work' => $this->workContract($budget),
            'b2b' => $this->b2b($budget, $options),
        ];
    }

    public function progressiveAnnualTax(float $base): float
    {
        $base = max(0.0, $base);
        if ($base <= 120000) {
            return $this->round(max(0.0, ($base * 0.12) - 3600));
        }

        return $this->round(10800 + (($base - 120000) * 0.32));
    }

    public function monthlyProgressiveTax(float $monthlyBase): float
    {
        return $this->round($this->progressiveAnnualTax(max(0.0, $monthlyBase) * 12) / 12);
    }

    /**
     * @return array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}
     */
    private function employment(float $budget): array
    {
        $gross = $this->round($budget / 1.2048);
        $social = $this->round($gross * 0.1371);
        $healthBase = max(0.0, $gross - $social);
        $health = $this->round($healthBase * 0.09);
        $tax = $this->monthlyProgressiveTax($gross - $social - 250);

        return $this->result($budget, $gross, $social, $health, $tax, 0.0);
    }

    /**
     * @return array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}
     */
    private function mandate(float $budget, bool $studentUnder26): array
    {
        if ($studentUnder26) {
            $taxableAnnual = max(0.0, ($budget * 12) - 85528);
            $tax = $this->round($this->progressiveAnnualTax($taxableAnnual * 0.8) / 12);

            return $this->result($budget, $budget, 0.0, 0.0, $tax, 0.0);
        }

        $gross = $this->round($budget / 1.2048);
        $social = $this->round($gross * 0.1371);
        $baseAfterSocial = max(0.0, $gross - $social);
        $health = $this->round($baseAfterSocial * 0.09);
        $tax = $this->monthlyProgressiveTax($baseAfterSocial * 0.8);

        return $this->result($budget, $gross, $social, $health, $tax, 0.0);
    }

    /**
     * @return array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}
     */
    private function workContract(float $budget): array
    {
        $tax = $this->monthlyProgressiveTax($budget * 0.8);

        return $this->result($budget, $budget, 0.0, 0.0, $tax, 0.0);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}
     */
    private function b2b(float $budget, array $options): array
    {
        $costs = max(0.0, (float) ($options['costs'] ?? 0));
        $zus = (string) ($options['zus'] ?? 'standard');
        $social = match ($zus) {
            'start' => 0.0,
            'preferential' => 456.18,
            default => 1926.76,
        };

        $income = max(0.0, $budget - $costs - $social);
        $taxation = (string) ($options['taxation'] ?? 'progressive');

        if ($taxation === 'linear') {
            $health = max(432.54, $this->round($income * 0.049));
            $tax = $this->round(max(0.0, $income - min($health, 1175.0)) * 0.19);
        } elseif ($taxation === 'lump') {
            $annualRevenue = $budget * 12;
            $health = $annualRevenue <= 60000 ? 498.35 : ($annualRevenue <= 300000 ? 830.58 : 1495.04);
            $rate = max(0.0, (float) ($options['lumpRate'] ?? 12)) / 100;
            $tax = $this->round(max(0.0, $budget - $social - ($health * 0.5)) * $rate);
        } else {
            $health = max(432.54, $this->round($income * 0.09));
            $tax = $this->monthlyProgressiveTax($income);
        }

        return $this->result($budget, $budget, $social, $health, $tax, $costs);
    }

    /**
     * @return array{cost: float, gross: float, social: float, health: float, tax: float, businessCosts: float, net: float}
     */
    private function result(float $cost, float $gross, float $social, float $health, float $tax, float $businessCosts): array
    {
        return [
            'cost' => $this->round($cost),
            'gross' => $this->round($gross),
            'social' => $this->round($social),
            'health' => $this->round($health),
            'tax' => $this->round($tax),
            'businessCosts' => $this->round($businessCosts),
            'net' => $this->round($gross - $social - $health - $tax - $businessCosts),
        ];
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
