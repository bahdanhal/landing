<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Application\IssueGrouper;
use PHPUnit\Framework\TestCase;

final class IssueGrouperTest extends TestCase
{
    public function testItGroupsRepeatedFindingsWithoutLosingEvidence(): void
    {
        $groups = (new IssueGrouper())->group([
            ['severity' => 'warning', 'code' => 'missing-title', 'title' => 'Missing title', 'detail' => '/one', 'evidence' => ['url' => '/one']],
            ['severity' => 'warning', 'code' => 'missing-title', 'title' => 'Missing title', 'detail' => '/two'],
            ['severity' => 'info', 'code' => 'thin-page', 'title' => 'Thin', 'detail' => '/three'],
        ]);

        self::assertCount(2, $groups);
        self::assertCount(2, $groups[0]['occurrences']);
        self::assertSame(['url' => '/one'], $groups[0]['occurrences'][0]['evidence']);
        self::assertSame([], $groups[0]['occurrences'][1]['evidence']);
    }
}
