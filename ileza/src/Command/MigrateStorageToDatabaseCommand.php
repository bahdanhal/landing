<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LeadEntity;
use App\Entity\PageViewEntity;
use App\Entity\PriceObservationEntity;
use App\Entity\PriceTipEntity;
use App\Entity\ProductRequestEntity;
use App\Lead\Domain\Lead;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceTip;
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
        private readonly string $marketDataDirectory,
        private readonly string $contactLeadDirectory,
        private readonly string $analyticsDataDirectory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrating JSON / JSONL file storage to Database');

        $observationCount = $this->importPriceObservations($io);
        $leadCount = $this->importLeads($io);
        $requestCount = $this->importProductRequests($io);
        $tipCount = $this->importPriceTips($io);
        $viewCount = $this->importPageViews($io);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Migration completed: %d observations, %d leads, %d product requests, %d price tips, %d page views.',
            $observationCount,
            $leadCount,
            $requestCount,
            $tipCount,
            $viewCount
        ));

        return Command::SUCCESS;
    }

    private function importPriceObservations(SymfonyStyle $io): int
    {
        $dir = rtrim($this->marketDataDirectory, '/');
        $files = glob($dir . '/*.json') ?: [];
        $count = 0;
        $repo = $this->entityManager->getRepository(PriceObservationEntity::class);

        foreach ($files as $file) {
            try {
                $raw = (string) file_get_contents($file);
                $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    continue;
                }

                if (array_is_list($data)) {
                    $items = $data;
                } elseif (isset($data['history']) && is_array($data['history'])) {
                    $items = $data['history'];
                } else {
                    $items = [$data];
                }
                foreach ($items as $item) {
                    if (!isset($item['product_slug'], $item['observed_at'], $item['median_grosz'])) {
                        continue;
                    }

                    $obs = PriceObservation::fromArray($item);
                    $existing = $repo->findOneBy([
                        'productSlug' => $obs->productSlug,
                        'observedAt' => $obs->observedAt,
                    ]);

                    if ($existing === null) {
                        $entity = new PriceObservationEntity(
                            $obs->productSlug,
                            $obs->observedAt,
                            $obs->medianGrosz,
                            $obs->lowGrosz,
                            $obs->highGrosz,
                            $obs->sampleSize,
                            $obs->confidence,
                            $obs->summary !== '' ? $obs->summary : null,
                            $obs->methodology !== '' ? $obs->methodology : PriceObservation::METHODOLOGY_MANUAL
                        );
                        $this->entityManager->persist($entity);
                        ++$count;
                    }
                }
            } catch (\Throwable $e) {
                $io->warning(sprintf('Error reading %s: %s', basename($file), $e->getMessage()));
            }
        }

        return $count;
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

    private function importProductRequests(SymfonyStyle $io): int
    {
        $dir = rtrim($this->marketDataDirectory, '/') . '/requests';
        $files = glob($dir . '/product-requests-*.jsonl') ?: [];
        $count = 0;

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                try {
                    $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (!is_array($item)) {
                        continue;
                    }

                    $createdAt = new \DateTimeImmutable((string) ($item['timestamp'] ?? 'now'));
                    $entity = new ProductRequestEntity(
                        (string) ($item['product'] ?? ''),
                        (string) ($item['email'] ?? ''),
                        (string) ($item['ip_hash'] ?? ''),
                        $createdAt
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

    private function importPriceTips(SymfonyStyle $io): int
    {
        $dir = rtrim($this->marketDataDirectory, '/') . '/price-tips';
        $files = glob($dir . '/*.json') ?: [];
        $count = 0;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($files as $file) {
            try {
                $raw = (string) file_get_contents($file);
                $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    continue;
                }

                $tip = PriceTip::fromArray($data);
                if ($tip->expiresAt > $now) {
                    $entity = new PriceTipEntity(
                        $tip->productSlug,
                        $tip->listingUrl,
                        $tip->email,
                        $tip->ipHash,
                        $tip->submittedAt,
                        $tip->expiresAt
                    );
                    $this->entityManager->persist($entity);
                    ++$count;
                }
            } catch (\Throwable) {
                continue;
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
