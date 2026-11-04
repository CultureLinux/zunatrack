<?php

namespace App\Repository;

use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Series> */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Series::class); }

    /** @return list<Series> */
    public function findByActor(string $actor): array
    {
        $needle = $this->normalizeActor($actor);
        if ($needle === '') return [];

        return array_values(array_filter(
            $this->findBy([], ['title' => 'ASC']),
            fn (Series $series): bool => array_any($series->getCast(), fn (array $cast): bool => $this->normalizeActor((string) ($cast['name'] ?? '')) === $needle),
        ));
    }

    private function normalizeActor(string $name): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? '')); }
}
