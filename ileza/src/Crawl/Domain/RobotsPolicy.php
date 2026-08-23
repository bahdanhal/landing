<?php

declare(strict_types=1);

namespace App\Crawl\Domain;

final readonly class RobotsPolicy
{
    /** @return array{rules:list<array{path:string,allow:bool}>,crawl_delay_ms:int} */
    public function parse(string $body): array
    {
        $groups = [];
        $agents = [];
        $directives = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = strtolower($name);
            if ($name === 'user-agent') {
                if ($directives !== []) {
                    $groups[] = compact('agents', 'directives');
                    $agents = [];
                    $directives = [];
                }
                $agents[] = strtolower($value);
            } elseif ($agents !== []) {
                $directives[] = ['name' => $name, 'value' => $value];
            }
        }
        if ($agents !== [] || $directives !== []) {
            $groups[] = compact('agents', 'directives');
        }

        $selected = array_values(array_filter($groups, static fn (array $group) => array_filter(
            $group['agents'],
            static fn (string $agent) => $agent === '*' || str_contains('openseoaudit', $agent),
        ) !== []));
        $specific = array_values(array_filter($selected, static fn (array $group) => !in_array('*', $group['agents'], true)));
        if ($specific !== []) {
            $selected = $specific;
        }

        $rules = [];
        $delay = 0;
        foreach ($selected as $group) {
            foreach ($group['directives'] as $directive) {
                if (in_array($directive['name'], ['allow', 'disallow'], true) && $directive['value'] !== '') {
                    $rules[] = ['path' => $directive['value'], 'allow' => $directive['name'] === 'allow'];
                } elseif ($directive['name'] === 'crawl-delay' && is_numeric($directive['value'])) {
                    $delay = max($delay, min(10_000, (int) round((float) $directive['value'] * 1000)));
                }
            }
        }

        return ['rules' => $rules, 'crawl_delay_ms' => $delay];
    }

    /**
     * @param array{rules?: list<array{path: string, allow: bool}>, crawl_delay_ms?: int} $policy
     */
    public function allows(string $url, array $policy): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $candidate = ($path === '' ? '/' : $path) . ($query === null ? '' : '?' . $query);
        $matches = array_values(array_filter(
            $policy['rules'] ?? [],
            fn (array $rule) => $this->matches($candidate, $rule['path']),
        ));
        if ($matches === []) {
            return true;
        }
        usort($matches, fn (array $a, array $b) => $this->specificity($b['path']) <=> $this->specificity($a['path']));
        $longest = $this->specificity($matches[0]['path']);
        $ties = array_filter($matches, fn (array $rule) => $this->specificity($rule['path']) === $longest);

        return array_filter($ties, static fn (array $rule) => $rule['allow']) !== [];
    }

    private function matches(string $candidate, string $rule): bool
    {
        $anchored = str_ends_with($rule, '$');
        if ($anchored) {
            $rule = substr($rule, 0, -1);
        }
        $pattern = str_replace('\\*', '.*', preg_quote($rule, '#'));

        return preg_match('#^' . $pattern . ($anchored ? '$' : '') . '#u', $candidate) === 1;
    }

    private function specificity(string $rule): int
    {
        return strlen(str_replace(['*', '$'], '', $rule));
    }
}
