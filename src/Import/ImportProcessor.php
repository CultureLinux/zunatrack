<?php

declare(strict_types=1);

namespace App\Import;

use App\Catalog\CatalogService;
use App\Entity\EpisodeProgress;
use App\Entity\ImportItem;
use App\Entity\LibraryEntry;
use App\Repository\LibraryEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImportProcessor
{
    public function __construct(private CatalogService $catalog, private LibraryEntryRepository $entries, private EntityManagerInterface $em) {}

    public function process(ImportItem $item): void
    {
        $payload = ImportPayload::fromArray($item->getPayload());
        $matches = [];
        foreach ($this->catalog->rawSearch($payload->title) as $row) {
            $show = $row['show'] ?? [];
            if ($this->normalize((string) ($show['name'] ?? '')) === $this->normalize($payload->title)) $matches[] = ['id'=>(string)$show['id'], 'title'=>(string)$show['name']];
        }
        if ($item->getSelectedCandidateId() !== null) $matches=array_values(array_filter($matches,fn(array $candidate):bool=>$candidate['id']===$item->getSelectedCandidateId()));
        if (count($matches) !== 1) { $item->unresolved($matches === [] ? 'Aucune correspondance TVmaze exacte.' : 'Plusieurs correspondances TVmaze exactes.', $matches); $item->getBatch()->record(ImportItem::STATE_UNRESOLVED); $this->em->flush(); return; }
        $series = $this->catalog->import($matches[0]['id']);
        $episodes = [];
        foreach ($series->getSeasons() as $season) foreach ($season->getEpisodes() as $episode) $episodes[$season->getNumber()][$episode->getNumber()] = $episode;
        foreach ($payload->seasons as $season) if (!isset($episodes[(int)$season])) { $item->unresolved('La structure des saisons ne correspond pas à TVmaze.', $matches); $item->getBatch()->record(ImportItem::STATE_UNRESOLVED); $this->em->flush(); return; }
        foreach ($payload->watchedEpisodes as $season => $numbers) foreach ($numbers as $number) if (!isset($episodes[(int)$season][(int)$number])) { $item->unresolved('La structure des épisodes ne correspond pas à TVmaze.', $matches); $item->getBatch()->record(ImportItem::STATE_UNRESOLVED); $this->em->flush(); return; }
        $entry = $this->entries->findForUserAndSeries($item->getBatch()->getUser(), $series);
        $state = ImportItem::STATE_MERGED;
        if (!$entry) { $entry = new LibraryEntry($item->getBatch()->getUser(), $series); $this->apply($entry, $payload); $this->em->persist($entry); $state = ImportItem::STATE_IMPORTED; }
        foreach ($payload->watchedEpisodes as $season => $numbers) foreach ($numbers as $number) {
            $episode = $episodes[(int)$season][(int)$number];
            if (!$this->em->getRepository(EpisodeProgress::class)->findOneBy(['user'=>$item->getBatch()->getUser(), 'episode'=>$episode])) $this->em->persist(new EpisodeProgress($item->getBatch()->getUser(), $episode));
        }
        $item->complete($state, $series->getPosterUrl()); $item->getBatch()->record($state); $this->em->flush();
    }

    private function apply(LibraryEntry $entry, ImportPayload $payload): void { $map=['Completed'=>LibraryEntry::STATUS_COMPLETED,'In progress'=>LibraryEntry::STATUS_WATCHING,'Planning'=>LibraryEntry::STATUS_WATCHLIST,'Paused'=>LibraryEntry::STATUS_PAUSED,'Dropped'=>LibraryEntry::STATUS_DROPPED,...array_combine(array_keys(LibraryEntry::STATUSES),array_keys(LibraryEntry::STATUSES))]; if(isset($map[$payload->status ?? '']))$entry->setStatus($map[$payload->status]); if($payload->notes)$entry->setNote($payload->notes); if($payload->startedAt)$entry->setStartedAt(new \DateTimeImmutable($payload->startedAt)); if($payload->finishedAt)$entry->setFinishedAt(new \DateTimeImmutable($payload->finishedAt)); }
    private function normalize(string $value): string { $value=mb_strtolower(trim($value)); return preg_replace('/[^a-z0-9]+/u','',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) ?: $value) ?: ''; }
}
