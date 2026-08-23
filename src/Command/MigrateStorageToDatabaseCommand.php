<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LeadEntity;
use App\Entity\PageViewEntity;
use App\Lead\Domain\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-json-to-database',
    description: 'Import existing JSON / JSONL files from var/ into the PostgreSQL database'
)]
final class MigrateStorageToDatabaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $contactLeadDirectory,
        private readonly string $analyticsDataDirectory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrating JSON / JSONL file storage to Database');

        $leadCount = $this->importLeads($io);
        $viewCount = $this->importPageViews($io);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Migration completed: %d leads, %d page views.',
            $leadCount,
            $viewCount
        ));

        return Command::SUCCESS;
    }

    private function importLeads(SymfonyStyle $io): int
    {
        $dir = rtrim($this->contactLeadDirectory, '/');
        $files = glob($dir . '/leads-*.jsonl') ?: [];
        $count = 0;

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                try {
                    $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (!is_array($item)) {
                        continue;
                    }

                    $lead = Lead::fromArray($item);
                    $entity = new LeadEntity(
                        $lead->email,
                        $lead->phone,
                        $lead->message,
                        $lead->ipHash,
                        $lead->source,
                        $lead->createdAt
                    );
                    $this->entityManager->persist($entity);
                    ++$count;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $count;
    }

    private function importPageViews(SymfonyStyle $io): int
    {
        $dir = rtrim($this->analyticsDataDirectory, '/');
        $files = glob($dir . '/*.jsonl') ?: [];
        $count = 0;

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                try {
                    $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (!is_array($item) || !isset($item['occurred_at'], $item['visitor_hash'], $item['path'], $item['source'])) {
                        continue;
                    }

                    $occurredAt = new \DateTimeImmutable((string) $item['occurred_at']);
                    $entity = new PageViewEntity(
                        $occurredAt,
                        (string) $item['visitor_hash'],
                        (string) $item['path'],
                        (string) $item['source'],
                        isset($item['referrer_host']) ? (string) $item['referrer_host'] : null,
                    );
                    $this->entityManager->persist($entity);
                    ++$count;
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $count;
    }
}
