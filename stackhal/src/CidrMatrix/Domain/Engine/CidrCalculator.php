<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Engine;

use App\CidrMatrix\Domain\Model\CidrBlock;
use App\CidrMatrix\Domain\Model\CidrDiagnostic;
use App\CidrMatrix\Domain\Model\CidrMatrixResult;
use App\CidrMatrix\Domain\Model\IpVersion;
use App\CidrMatrix\Domain\Model\SubnetCollision;

final readonly class CidrCalculator
{
    private const int MAX_SUBNETS_PER_REQUEST = 100;

    /**
     * @param list<string> $cidrInputs
     */
    public function analyze(
        array $cidrInputs,
        ?int $requestedFreePrefix = null,
        ?string $parentCidrInput = null,
    ): CidrMatrixResult {
        $parsedBlocks = [];
        $diagnostics = [];
        $warnings = [];

        $inputCount = 0;
        foreach ($cidrInputs as $rawInput) {
            $rawInput = trim($rawInput);
            if ($rawInput === '') {
                continue;
            }

            if ($inputCount >= self::MAX_SUBNETS_PER_REQUEST) {
                break;
            }

            $block = CidrBlock::parse($rawInput);
            if ($block === null) {
                $diagnostics[] = new CidrDiagnostic(
                    code: 'ERR_INVALID_CIDR',
                    severity: 'error',
                    title: 'Invalid CIDR Format',
                    description: sprintf('Provided string "%s" is not a valid IPv4 or IPv6 CIDR notation.', $rawInput),
                    context: $rawInput
                );
                continue;
            }

            $inputCount++;
            $parsedBlocks[] = $block;

            if (!$block->isCanonical) {
                if (!in_array('WARN_NON_CANONICAL_IP', $warnings, true)) {
                    $warnings[] = 'WARN_NON_CANONICAL_IP';
                }
                $diagnostics[] = new CidrDiagnostic(
                    code: 'WARN_NON_CANONICAL_IP',
                    severity: 'warning',
                    title: 'Host Bits Set in Network Address',
                    description: sprintf(
                        'IP address "%s" contained host bits for prefix /%d; normalized to "%s".',
                        $block->ipAddress,
                        $block->prefixLength,
                        $block->normalizedCidr
                    ),
                    context: $block->rawInput
                );
            }
        }

        // Check collisions and containments
        $collisions = [];
        $containments = [];
        $count = count($parsedBlocks);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $blockA = $parsedBlocks[$i];
                $blockB = $parsedBlocks[$j];

                if ($blockA->version !== $blockB->version) {
                    continue;
                }

                if ($blockA->overlaps($blockB)) {
                    $overlapStart = max($blockA->startBytes, $blockB->startBytes);
                    $overlapEnd = min($blockA->endBytes, $blockB->endBytes);

                    $startIp = inet_ntop($overlapStart);
                    $endIp = inet_ntop($overlapEnd);

                    if ($startIp !== false && $endIp !== false) {
                        $overlapCidr = $this->calculateOverlapCidr(
                            $overlapStart,
                            $overlapEnd,
                            $blockA->version,
                            $blockA,
                            $blockB
                        );

                        $collisions[] = new SubnetCollision(
                            cidrA: $blockA->normalizedCidr,
                            cidrB: $blockB->normalizedCidr,
                            overlapCidr: $overlapCidr,
                            startIp: $startIp,
                            endIp: $endIp,
                        );

                        if (!in_array('ERR_OVERLAP_COLLISION', $warnings, true)) {
                            $warnings[] = 'ERR_OVERLAP_COLLISION';
                        }

                        $diagnostics[] = new CidrDiagnostic(
                            code: 'ERR_OVERLAP_COLLISION',
                            severity: 'error',
                            title: 'Subnet Overlap Collision',
                            description: sprintf(
                                'Subnets %s and %s overlap in address range %s - %s.',
                                $blockA->normalizedCidr,
                                $blockB->normalizedCidr,
                                $startIp,
                                $endIp
                            ),
                            context: sprintf('%s <-> %s', $blockA->normalizedCidr, $blockB->normalizedCidr)
                        );
                    }
                }

                if ($blockA->contains($blockB) && $blockA->normalizedCidr !== $blockB->normalizedCidr) {
                    $containments[] = [
                        'parent' => $blockA->normalizedCidr,
                        'child' => $blockB->normalizedCidr,
                    ];
                    if (!in_array('WARN_CONTAINMENT', $warnings, true)) {
                        $warnings[] = 'WARN_CONTAINMENT';
                    }
                    $diagnostics[] = new CidrDiagnostic(
                        code: 'WARN_CONTAINMENT',
                        severity: 'warning',
                        title: 'Parent-Child Containment',
                        description: sprintf('%s completely encompasses smaller subnet %s.', $blockA->normalizedCidr, $blockB->normalizedCidr),
                        context: sprintf('%s > %s', $blockA->normalizedCidr, $blockB->normalizedCidr)
                    );
                } elseif ($blockB->contains($blockA) && $blockB->normalizedCidr !== $blockA->normalizedCidr) {
                    $containments[] = [
                        'parent' => $blockB->normalizedCidr,
                        'child' => $blockA->normalizedCidr,
                    ];
                    if (!in_array('WARN_CONTAINMENT', $warnings, true)) {
                        $warnings[] = 'WARN_CONTAINMENT';
                    }
                    $diagnostics[] = new CidrDiagnostic(
                        code: 'WARN_CONTAINMENT',
                        severity: 'warning',
                        title: 'Parent-Child Containment',
                        description: sprintf('%s completely encompasses smaller subnet %s.', $blockB->normalizedCidr, $blockA->normalizedCidr),
                        context: sprintf('%s > %s', $blockB->normalizedCidr, $blockA->normalizedCidr)
                    );
                }
            }
        }

        // Free Subnet Allocation logic
        $freeSubnetCidr = null;
        if ($requestedFreePrefix !== null) {
            $parentBlock = null;
            if ($parentCidrInput !== null) {
                $parentBlock = CidrBlock::parse($parentCidrInput);
            } elseif (count($parsedBlocks) > 0) {
                $parentBlock = $parsedBlocks[0];
            }

            if ($parentBlock !== null && $requestedFreePrefix >= $parentBlock->prefixLength) {
                $freeSubnetCidr = $this->findFreeSubnet(
                    $parentBlock,
                    $requestedFreePrefix,
                    $parsedBlocks
                );

                if ($freeSubnetCidr !== null) {
                    $diagnostics[] = new CidrDiagnostic(
                        code: 'INFO_FREE_ALLOCATION_FOUND',
                        severity: 'info',
                        title: 'Free Allocation Available',
                        description: sprintf(
                            'Found available free subnet %s matching requested prefix /%d.',
                            $freeSubnetCidr,
                            $requestedFreePrefix
                        ),
                        context: $freeSubnetCidr
                    );
                } else {
                    $diagnostics[] = new CidrDiagnostic(
                        code: 'ERR_SUBNET_EXHAUSTED',
                        severity: 'error',
                        title: 'Subnet Space Exhausted',
                        description: sprintf(
                            'No contiguous free subnet of prefix /%d available inside %s.',
                            $requestedFreePrefix,
                            $parentBlock->normalizedCidr
                        ),
                        context: (string) $requestedFreePrefix
                    );
                }
            }
        }

        // Matrix Grid Partitioning
        $matrixGrid = $this->buildMatrixGrid($parsedBlocks, $collisions);

        return new CidrMatrixResult(
            parsedBlocks: $parsedBlocks,
            hasCollisions: count($collisions) > 0,
            collisionCount: count($collisions),
            collisions: $collisions,
            containments: $containments,
            freeSubnetCidr: $freeSubnetCidr,
            matrixGrid: $matrixGrid,
            diagnostics: $diagnostics,
            warnings: array_values(array_unique($warnings)),
        );
    }

    private function calculateOverlapCidr(
        string $overlapStart,
        string $overlapEnd,
        IpVersion $version,
        CidrBlock $blockA,
        CidrBlock $blockB
    ): string {
        // If one contains the other, overlap CIDR is the smaller block
        if ($blockA->contains($blockB)) {
            return $blockB->normalizedCidr;
        }
        if ($blockB->contains($blockA)) {
            return $blockA->normalizedCidr;
        }

        $startIp = inet_ntop($overlapStart);
        $endIp = inet_ntop($overlapEnd);

        if ($startIp === false || $endIp === false) {
            return 'unknown';
        }

        return sprintf('%s - %s', $startIp, $endIp);
    }

    /**
     * @param list<CidrBlock> $existingBlocks
     */
    private function findFreeSubnet(
        CidrBlock $parent,
        int $requestedPrefix,
        array $existingBlocks
    ): ?string {
        $currBytes = $parent->startBytes;
        $maxAttempts = 256;
        $attempts = 0;

        while ($currBytes <= $parent->endBytes && $attempts < $maxAttempts) {
            $attempts++;
            $candidateIp = inet_ntop($currBytes);
            if ($candidateIp === false) {
                break;
            }

            $candidateCidrString = sprintf('%s/%d', $candidateIp, $requestedPrefix);
            $candidateBlock = CidrBlock::parse($candidateCidrString);
            if ($candidateBlock === null || !$parent->contains($candidateBlock)) {
                break;
            }

            $hasConflict = false;
            foreach ($existingBlocks as $existing) {
                if ($existing->overlaps($candidateBlock)) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                return $candidateBlock->normalizedCidr;
            }

            $nextBytes = $this->addBlockSize($currBytes, $requestedPrefix, $parent->version);
            if ($nextBytes === null || $nextBytes <= $currBytes) {
                break;
            }
            $currBytes = $nextBytes;
        }

        return null;
    }

    private function addBlockSize(string $binaryBytes, int $prefixLength, IpVersion $version): ?string
    {
        if ($version === IpVersion::V4) {
            $unpacked = unpack('N', $binaryBytes);
            if ($unpacked === false) {
                return null;
            }
            $val = $unpacked[1];
            $hostBits = 32 - $prefixLength;
            if ($hostBits >= 32) {
                return null;
            }
            $step = 1 << $hostBits;
            $next = $val + $step;
            if ($next > 0xFFFFFFFF) {
                return null;
            }
            return pack('N', $next);
        }

        $unpacked = unpack('C*', $binaryBytes);
        if ($unpacked === false) {
            return null;
        }
        $bytes = array_values($unpacked);
        $byteIndex = (int) floor(($prefixLength - 1) / 8);
        if ($byteIndex < 0) {
            $byteIndex = 0;
        }
        $bitOffset = ($prefixLength - 1) % 8;
        $increment = 1 << (7 - $bitOffset);

        $carry = $increment;
        for ($i = $byteIndex; $i >= 0; $i--) {
            $sum = $bytes[$i] + $carry;
            $bytes[$i] = $sum & 0xFF;
            $carry = $sum >> 8;
            if ($carry === 0) {
                break;
            }
        }
        if ($carry > 0) {
            return null;
        }

        return pack('C*', ...$bytes);
    }

    /**
     * @param list<CidrBlock> $blocks
     * @param list<SubnetCollision> $collisions
     * @return list<array<string, mixed>>
     */
    private function buildMatrixGrid(array $blocks, array $collisions): array
    {
        if (count($blocks) === 0) {
            return [];
        }

        $grid = [];
        foreach ($blocks as $idx => $block) {
            $hasCollision = false;
            foreach ($collisions as $col) {
                if ($col->cidrA === $block->normalizedCidr || $col->cidrB === $block->normalizedCidr) {
                    $hasCollision = true;
                    break;
                }
            }

            $grid[] = [
                'id' => sprintf('block-%d', $idx + 1),
                'cidr' => $block->normalizedCidr,
                'raw_input' => $block->rawInput,
                'version' => $block->version->value,
                'network_ip' => $block->networkIp,
                'broadcast_ip' => $block->broadcastIp,
                'prefix_length' => $block->prefixLength,
                'total_hosts' => $block->totalHosts,
                'status' => $hasCollision ? 'collision' : 'assigned',
            ];
        }

        return $grid;
    }
}
