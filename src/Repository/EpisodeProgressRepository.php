<?php
namespace App\Repository;
use App\Entity\Episode; use App\Entity\EpisodeProgress; use App\Entity\User; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;
/** @extends ServiceEntityRepository<EpisodeProgress> */
class EpisodeProgressRepository extends ServiceEntityRepository { public function __construct(ManagerRegistry $registry){parent::__construct($registry,EpisodeProgress::class);} /** @param list<Episode> $episodes @return list<int> */ public function watchedEpisodeIds(User $user,array $episodes):array {if($episodes===[])return [];return array_map('intval',$this->createQueryBuilder('progress')->select('IDENTITY(progress.episode)')->where('progress.user=:user')->andWhere('progress.episode IN (:episodes)')->setParameter('user',$user)->setParameter('episodes',$episodes)->getQuery()->getSingleColumnResult());} }
