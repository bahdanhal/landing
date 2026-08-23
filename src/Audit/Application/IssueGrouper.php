<?php

declare(strict_types=1);

namespace App\Audit\Application;

final readonly class IssueGrouper
{
    /**
     * @param list<array{severity: string, code: string, title: string, detail: string, evidence?: array<mixed>}> $issues
     * @return list<array{severity: string, code: string, title: string, occurrences: list<array{detail: string, evidence: array<mixed>}>}>
     */
    public function group(array $issues): array
    {
        $groups = [];
        foreach ($issues as $issue) {
            $key = $issue['severity'] . '|' . $issue['code'];
            $groups[$key] ??= ['severity' => $issue['severity'], 'code' => $issue['code'], 'title' => $issue['title'], 'occurrences' => []];
            $groups[$key]['occurrences'][] = ['detail' => $issue['detail'], 'evidence' => $issue['evidence'] ?? []];
        }

        return array_values($groups);
    }
}
