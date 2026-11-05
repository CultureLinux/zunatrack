<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\EpisodeProgress;
use App\Entity\User;
use App\Repository\CatalogSourceRepository;
use App\Repository\LibraryEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ZunaTrackCsvExporter
{
    public function __construct(private LibraryEntryRepository $entries, private CatalogSourceRepository $sources, private EntityManagerInterface $em) {}
    public function stream(User $user): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($user): void {
            $out=fopen('php://output','wb');
            fputcsv($out,['zunatrack_version','provider','external_id','media_type','title','status','notes','start_date','end_date','season_number','episode_number','watched_at'], ',', '"', '');
            foreach($this->entries->findForUser($user) as $entry) {
                $source=$this->sources->findOneBy(['series'=>$entry->getSeries()]); if(!$source)continue;
                $base=['zunatrack-v1',$source->getProvider(),$source->getExternalId(),'tv',$entry->getSeries()->getTitle(),$entry->getStatus(),$entry->getNote(),$entry->getStartedAt()?->format(DATE_ATOM),$entry->getFinishedAt()?->format(DATE_ATOM),'','','']; fputcsv($out,$base, ',', '"', '');
                foreach($this->em->getRepository(EpisodeProgress::class)->findBy(['user'=>$user]) as $progress){$episode=$progress->getEpisode();$season=$episode->getSeason();if($season->getSeries()->getId()!==$entry->getSeries()->getId())continue;$row=$base;$row[3]='episode';$row[9]=$season->getNumber();$row[10]=$episode->getNumber();$row[11]=$progress->getWatchedAt()->format(DATE_ATOM);fputcsv($out,$row, ',', '"', '');}
            }
            fclose($out);
        });
        $response->headers->set('Content-Type','text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="zunatrack-export-%s.csv"', (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Ymd-His')));
        return $response;
    }
}
