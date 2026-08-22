<?php

declare(strict_types=1);

namespace App\Command;

use App\Audit\Application\SiteAuditor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'seo:audit', description: 'Run a deterministic technical SEO audit')]
final class AuditCommand extends Command
{
    public function __construct(private readonly SiteAuditor $auditor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Website URL to audit')
            ->addOption('summary', null, InputOption::VALUE_NONE, 'Output compact results without page details')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Ignore a cached report and crawl again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report = $this->auditor->audit((string) $input->getArgument('url'), (bool) $input->getOption('refresh'));
            if ($input->getOption('summary')) {
                $report = [
                    'target' => $report['target'],
                    'origin' => $report['origin'],
                    'duration_ms' => $report['duration_ms'],
                    'score' => $report['score'],
                    'counts' => $report['counts'],
                    'summary' => $report['summary'],
                    'cache' => $report['cache'],
                    'ai_summary' => $report['ai_summary'],
                    'issues' => array_map(static fn (array $issue) => [
                        'severity' => $issue['severity'],
                        'code' => $issue['code'],
                        'detail' => $issue['detail'],
                    ], $report['issues']),
                ];
            }
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
