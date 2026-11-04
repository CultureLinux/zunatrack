<?php

namespace App\Catalog;

use App\Entity\CatalogSource;
use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Series;
use App\Repository\CatalogSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CatalogService
{
    public function __construct(private TvMazeCatalogProvider $provider, private CacheInterface $cache, private CatalogSourceRepository $sources, private EntityManagerInterface $em, private RateLimiterFactory $tvmazeApiLimiter, private PosterCache $posters, private CatalogMetrics $metrics) {}
    public function search(string $query): array { $key='catalog.tvmaze.search.'.hash('xxh3',mb_strtolower(trim($query))); $cached=$this->cache->get($key,function(ItemInterface $item)use($query){$item->expiresAfter(3600);$this->guardRateLimit();$this->metrics->hit('provider');return $this->provider->search($query);});$this->metrics->hit('local');return array_map(fn(array $row)=>[...$row,'posterUrl'=>$this->posters->store($row['posterUrl'])],$cached); }
    public function rawSearch(string $query): array { $this->guardRateLimit(); $this->metrics->hit('provider'); return $this->provider->rawSearch($query); }
    public function rawShow(string $id): array { $this->guardRateLimit(); $this->metrics->hit('provider'); return $this->provider->rawShow($id); }
    public function rawCast(string $id): array { $this->guardRateLimit(); $this->metrics->hit('provider'); return $this->provider->rawCast($id); }
    public function enrichFromSnapshot(Series $series, ?int $episodeSeason=null): void { $source=$this->sources->findOneBy(['series'=>$series]); if(!$source)return; if(!array_key_exists('cast',$source->getPayload()['_embedded']??[]))$this->refreshCast($source); $this->hydrate($series,$source->getPayload(),$episodeSeason); $this->em->flush(); }
    public function import(string $id): Series { $source=$this->sources->findByProviderId('tvmaze',$id); if($source?->isFresh()){$this->metrics->hit('local');$this->hydrate($source->getSeries(),$source->getPayload());$this->em->flush();return $source->getSeries();}$this->guardRateLimit();$this->metrics->hit('provider');$payload=$this->provider->fetch($id);$series=$source?->getSeries()??new Series();$this->hydrate($series,$payload);if(!$source){$source=new CatalogSource('tvmaze',$id,$series);$this->em->persist($source);}$source->refresh($payload,new \DateTimeImmutable('+24 hours'));$this->em->persist($series);$this->em->flush();return $series; }

    private function refreshCast(CatalogSource $source): void
    {
        $this->guardRateLimit(); $this->metrics->hit('provider'); $castPayload=$this->provider->fetchCast($source->getExternalId()); $payload=$source->getPayload(); $payload['_embedded']['cast']=$castPayload['_embedded']['cast']??[]; $source->refresh($payload,new \DateTimeImmutable('+24 hours')); $this->hydrate($source->getSeries(),$payload);
    }

    private function hydrate(Series $series, array $payload, ?int $episodeSeason=null): void
    {
        $series->setTitle($payload['name'])->setSummary(isset($payload['summary'])?strip_tags((string)$payload['summary']):null)->setPosterUrl($this->posters->store($payload['image']['medium']??$payload['image']['original']??null))->setStatus($payload['status']??null)->setGenres($payload['genres']??[])->setPremieredAt($this->date($payload['premiered']??null))->setEndedAt($this->date($payload['ended']??null));
        if (array_key_exists('cast',$payload['_embedded']??[])) $series->setCast($this->cast($payload['_embedded']['cast']));
        $series->touch(); $seasons=[]; foreach($series->getSeasons() as $season)$seasons[$season->getNumber()]=$season;
        foreach(($payload['_embedded']['seasons']??[]) as $row){$number=(int)$row['number'];$season=$seasons[$number]??new Season($number);$season->setName($row['name']??null);$season->setCoverUrl($this->posters->store($row['image']['medium']??$row['image']['original']??null));$series->addSeason($season);$seasons[$number]=$season;}
        $episodes=[]; foreach($seasons as $season)foreach($season->getEpisodes() as $episode)$episodes[$season->getNumber()][$episode->getNumber()]=$episode;
        foreach(($payload['_embedded']['episodes']??[]) as $row){$number=(int)($row['number']??0);$seasonNumber=(int)($row['season']??0);$season=$seasons[$seasonNumber]??null;if(!$season||$number<1)continue;$episode=$episodes[$seasonNumber][$number]??new Episode($number);$episode->setTitle($row['name']??'Épisode '.$number);$episode->setSummary(isset($row['summary'])?strip_tags((string)$row['summary']):null);$episode->setAirDate($this->date($row['airdate']??null));$episode->setAiredAt($this->dateTime($row['airstamp']??null));$episode->setRuntime(isset($row['runtime'])?(int)$row['runtime']:null);if($episodeSeason===$seasonNumber)$episode->setCoverUrl($this->posters->store($row['image']['medium']??$row['image']['original']??null));$season->addEpisode($episode);}
    }

    private function cast(array $rows): array { return array_values(array_filter(array_map(fn(array $row)=>['name'=>(string)($row['person']['name']??''),'character'=>isset($row['character']['name'])?(string)$row['character']['name']:null,'imageUrl'=>$this->posters->store($row['person']['image']['medium']??$row['person']['image']['original']??null)],$rows),fn(array $actor)=>$actor['name']!=='')); }
    private function guardRateLimit(): void { if(!$this->tvmazeApiLimiter->create('catalog')->consume()->isAccepted())throw new \RuntimeException('La limite TVmaze est temporairement atteinte. Réessayez dans un instant.'); }
    private function date(?string $value): ?\DateTimeImmutable { return $value?new \DateTimeImmutable($value):null; }
    private function dateTime(?string $value): ?\DateTimeImmutable { return $value?new \DateTimeImmutable($value):null; }
}
