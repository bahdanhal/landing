<?php

namespace App\Audit\Application;

final class IssueGrouper
{
    /** @return list<array{severity:string,code:string,title:string,occurrences:list<array{detail:string,evidence:array}>}> */
    public function group(array $issues): array
    {
        $groups = [];
        foreach ($issues as $issue) {
            $key = $issue['severity'].'|'.$issue['code'];
            $groups[$key] ??= ['severity' => $issue['severity'], 'code' => $issue['code'], 'title' => $issue['title'], 'occurrences' => []];
            $groups[$key]['occurrences'][] = ['detail' => $issue['detail'], 'evidence' => $issue['evidence'] ?? []];
        }

        return array_values($groups);
    }
}
