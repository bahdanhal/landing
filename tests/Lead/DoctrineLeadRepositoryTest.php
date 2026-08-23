<?php

declare(strict_types=1);

namespace App\Tests\Lead;

use App\Lead\Domain\Lead;
use App\Lead\Infrastructure\DoctrineLeadRepository;
use App\Tests\DoctrineTestCase;

final class DoctrineLeadRepositoryTest extends DoctrineTestCase
{
    public function testSavesAndRetrievesLeads(): void
    {
        $repository = new DoctrineLeadRepository($this->entityManager);

        self::assertCount(0, $repository->all());

        $lead1 = Lead::create(
            'first@example.com',
            '+48 111 222 333',
            'Hello world',
            'ip-hash-1',
            'seo-audit'
        );
        $lead2 = Lead::create(
            'second@example.com',
            '+48 444 555 666',
            'Second message',
            'ip-hash-2',
            'tools'
        );

        $repository->save($lead1);
        $repository->save($lead2);

        $all = $repository->all();
        self::assertCount(2, $all);
        self::assertSame('second@example.com', $all[0]->email);
        self::assertSame('first@example.com', $all[1]->email);
    }
}
