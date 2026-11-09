<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LibraryEntry;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LibraryEntry> */
class LibraryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryEntry::class);
    }

    public function findForUserAndSeries(User $user, Series $series): ?LibraryEntry
    {
        return $this->findOneBy(['user' => $user, 'series' => $series]);
    }

    /** @return list<LibraryEntry> */
    public function findForUser(User $user, ?string $status = null, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('entry')->join('entry.series', 'series')->where('entry.user = :user')->setParameter('user', $user);
        if ($status !== null) {
            $qb->andWhere('entry.status = :status')->setParameter('status', $status);
        }if ($query !== null) {
            $qb->andWhere('LOWER(series.title) LIKE :query')->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        return $qb->orderBy('entry.updatedAt', 'DESC')->getQuery()->getResult();
    }
}
