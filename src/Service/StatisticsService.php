<?php

namespace App\Service;

use App\Entity\LibraryEntry;
use App\Entity\User;
use App\Repository\CatalogSourceRepository;
use App\Repository\EpisodeProgressRepository;
use App\Repository\LibraryEntryRepository;

final class StatisticsService
{
    public function __construct(
        private readonly EpisodeProgressRepository $progresses,
        private readonly LibraryEntryRepository $entries,
        private readonly CatalogSourceRepository $sources,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $entries = $this->entries->findForUser($user);
        $progresses = $this->progresses->findBy(['user' => $user]);
        $today = new \DateTimeImmutable('today');
        $days = [];
        for ($offset = 6; $offset >= 0; --$offset) {
            $date = $today->modify(sprintf('-%d days', $offset));
            $days[$date->format('Y-m-d')] = ['label' => $date->format('D'), 'count' => 0];
        }
        $watchedIds = [];
        $minutes = 0;
        foreach ($progresses as $progress) {
            $episode = $progress->getEpisode();
            $watchedIds[$episode->getId()] = true;
            $minutes += $episode->getRuntime() ?? 0;
            $day = $progress->getWatchedAt()->format('Y-m-d');
            if (isset($days[$day])) ++$days[$day]['count'];
        }

        $statuses = array_fill_keys(array_keys(LibraryEntry::STATUSES), 0);
        $catchUp = 0;
        $series = [];
        $genres = [];
        $networks = [];
        foreach ($entries as $entry) {
            ++$statuses[$entry->getStatus()];
            if ($entry->getStatus() === LibraryEntry::STATUS_COMPLETED) continue;
            $show = $entry->getSeries();
            $source = $this->sources->findOneBy(['series' => $show]);
            $payload = $source?->getPayload() ?? [];
            foreach ($show->getGenres() as $genre) $genres[$genre] = ($genres[$genre] ?? 0) + 1;
            $network = $payload['network']['name'] ?? $payload['webChannel']['name'] ?? null;
            if (is_string($network) && $network !== '') $networks[$network] = ($networks[$network] ?? 0) + 1;
            $total = $watched = 0;
            foreach ($show->getSeasons() as $season) {
                if ($season->getNumber() === 0) continue;
                foreach ($season->getEpisodes() as $episode) {
                    if (!$this->isAired($episode->getAirDate())) continue;
                    ++$total;
                    if (isset($watchedIds[$episode->getId()])) ++$watched;
                    elseif (!in_array($entry->getStatus(), [LibraryEntry::STATUS_PAUSED, LibraryEntry::STATUS_DROPPED], true)) ++$catchUp;
                }
            }
            if ($total > 0) $series[] = ['title' => $show->getTitle(), 'watched' => $watched, 'total' => $total, 'percent' => (int) round($watched * 100 / $total)];
        }
        usort($series, static fn (array $left, array $right): int => [$right['percent'], $right['watched'], $left['title']] <=> [$left['percent'], $left['watched'], $right['title']]);

        return [
            'empty' => $entries === [],
            'overview' => ['watched' => count($progresses), 'watchTime' => $this->formatDuration($minutes), 'completed' => $statuses[LibraryEntry::STATUS_COMPLETED], 'catchUp' => $catchUp],
            'dailyActivity' => array_values($days),
            'activityMaximum' => max(1, ...array_column($days, 'count')),
            'statuses' => array_map(static fn (string $key, string $label): array => ['label' => $label, 'count' => $statuses[$key]], array_keys(LibraryEntry::STATUSES), LibraryEntry::STATUSES),
            'topSeries' => array_slice($series, 0, 3),
            'topGenres' => $this->top($genres),
            'topNetworks' => $this->top($networks),
        ];
    }

    /** @param array<string, int> $tags @return list<array{name:string,count:int}> */
    private function top(array $tags): array { uksort($tags, static fn (string $a, string $b): int => [$tags[$b], $a] <=> [$tags[$a], $b]); return array_map(static fn (string $name, int $count): array => ['name' => $name, 'count' => $count], array_keys(array_slice($tags, 0, 3, true)), array_slice($tags, 0, 3, true)); }
    private function formatDuration(int $minutes): string { $days=intdiv($minutes,1440); $hours=intdiv($minutes%1440,60); $remaining=$minutes%60; $parts=[]; if($days)$parts[]=$days.' j'; if($hours)$parts[]=$hours.' h'; if($remaining || $parts===[])$parts[]=$remaining.' min'; return implode(' ',$parts); }
    private function isAired(?\DateTimeImmutable $airDate): bool { return $airDate !== null && $airDate <= new \DateTimeImmutable('today'); }
}
