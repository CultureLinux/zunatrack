<?php

declare(strict_types=1);

namespace App\Watching;

use App\Entity\Episode;
use App\Entity\EpisodeProgress;
use App\Entity\LibraryEntry;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use App\Repository\EpisodeProgressRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProgressService
{
    public function __construct(private EpisodeProgressRepository $progresses, private EntityManagerInterface $em)
    {
    }

    public function seasonProgress(User $user, Season $season): array
    {
        return $this->progress($user, $this->aired($season->getEpisodes()->toArray()));
    }

    public function seriesProgress(User $user, Series $series): array
    {
        return $this->progress($user, $this->standardAired($series));
    }

    public function allSeasonProgress(User $user, Series $series): array
    {
        $result = [];
        foreach ($series->getSeasons() as $season) {
            $result[$season->getNumber()] = $this->seasonProgress($user, $season);
        }

        return $result;
    }

    public function watchedMap(User $user, Season $season): array
    {
        return array_fill_keys($this->progresses->watchedEpisodeIds($user, $season->getEpisodes()->toArray()), true);
    }

    public function synchronizeLibraryStatus(User $user, LibraryEntry $entry): void
    {
        $progress = $this->seriesProgress($user, $entry->getSeries());
        $complete = $progress['complete'];
        if ($complete && !\in_array($entry->getStatus(), [LibraryEntry::STATUS_PAUSED, LibraryEntry::STATUS_DROPPED], true)) {
            $entry->setStatus(LibraryEntry::STATUS_COMPLETED);
        } elseif ($progress['watched'] > 0 && $entry->getStatus() === LibraryEntry::STATUS_WATCHLIST) {
            $entry->setStatus(LibraryEntry::STATUS_WATCHING);
        } elseif (!$complete && $entry->getStatus() === LibraryEntry::STATUS_COMPLETED) {
            $entry->setStatus(LibraryEntry::STATUS_WATCHING);
        }
        $this->em->flush();
    }

    /** @return array<int, bool> Episode IDs which still have an aired, unwatched predecessor. */
    public function catchUpMap(User $user, Season $season): array
    {
        $watched = $this->watchedMap($user, $season);
        $missingPrevious = false;
        $result = [];
        foreach ($season->getEpisodes() as $episode) {
            if (!$this->isAired($episode)) {
                continue;
            }
            $result[$episode->getId()] = $missingPrevious;
            if (!isset($watched[$episode->getId()])) {
                $missingPrevious = true;
            }
        }

        return $result;
    }

    public function toggleEpisode(User $user, LibraryEntry $entry, Episode $episode, bool $watched, bool $catchUp = false): void
    {
        if ($episode->getSeason()->getSeries()->getId() !== $entry->getSeries()->getId()) {
            throw new \LogicException();
        }
        $episodes = [$episode];
        if ($watched && $catchUp) {
            $episodes = array_values(array_filter($episode->getSeason()->getEpisodes()->toArray(), fn (Episode $candidate) => $candidate->getNumber() <= $episode->getNumber() && $this->isAired($candidate)));
        }
        $this->setWatched($user, $episodes, $watched);
    }

    public function toggleSeason(User $user, LibraryEntry $entry, Season $season, bool $watched): void
    {
        if ($season->getSeries()->getId() !== $entry->getSeries()->getId()) {
            throw new \LogicException();
        } $this->setWatched($user, $this->aired($season->getEpisodes()->toArray()), $watched);
    }

    public function toggleSeries(User $user, LibraryEntry $entry, bool $watched): void
    {
        $this->setWatched($user, $this->standardAired($entry->getSeries()), $watched);
    }

    public function nextEpisode(User $user, Series $series): ?array
    {
        $episodes = $this->standardAired($series);
        $watched = array_flip($this->progresses->watchedEpisodeIds($user, $episodes));
        foreach ($series->getSeasons() as $season) {
            foreach ($season->getEpisodes() as $episode) {
                if (\in_array($episode, $episodes, true) && !isset($watched[$episode->getId()])) {
                    return ['season' => $season, 'episode' => $episode];
                }
            }
        }

        return null;
    }

    /** @return array{season:Season,episode:?Episode}|null */
    public function resumeTarget(User $user, Series $series): ?array
    {
        $seasons = array_values(array_filter($series->getSeasons()->toArray(), fn (Season $season) => $season->getNumber() !== 0));
        if ($seasons === []) {
            $seasons = $series->getSeasons()->toArray();
        }
        if ($seasons === []) {
            return null;
        }

        $episodes = array_merge(...array_map(fn (Season $season) => $season->getEpisodes()->toArray(), $seasons));
        $watched = array_flip($this->progresses->watchedEpisodeIds($user, $episodes));
        foreach ($seasons as $season) {
            foreach ($season->getEpisodes() as $episode) {
                if ($this->isAired($episode) && !isset($watched[$episode->getId()])) {
                    return ['season' => $season, 'episode' => $episode];
                }
            }
        }
        foreach ($seasons as $season) {
            foreach ($season->getEpisodes() as $episode) {
                if (!isset($watched[$episode->getId()])) {
                    return ['season' => $season, 'episode' => $episode];
                }
            }
        }

        return ['season' => $seasons[array_key_last($seasons)], 'episode' => null];
    }

    private function progress(User $user, array $episodes): array
    {
        $total = \count($episodes);
        $watched = \count($this->progresses->watchedEpisodeIds($user, $episodes));

        return ['watched' => $watched, 'total' => $total, 'percent' => $total ? (int) round($watched * 100 / $total) : 0, 'complete' => $total > 0 && $watched === $total];
    }

    private function setWatched(User $user, array $episodes, bool $watched): void
    {
        foreach ($episodes as $episode) {
            $existing = $this->progresses->findOneBy(['user' => $user, 'episode' => $episode]);
            if ($watched && !$existing) {
                $this->em->persist(new EpisodeProgress($user, $episode));
            }if (!$watched && $existing) {
                $this->em->remove($existing);
            }
        } $this->em->flush();
    }

    private function aired(array $episodes): array
    {
        return array_values(array_filter($episodes, fn (Episode $episode) => $this->isAired($episode)));
    }

    private function standardAired(Series $series): array
    {
        $episodes = [];
        foreach ($series->getSeasons() as $season) {
            if ($season->getNumber() !== 0) {
                foreach ($season->getEpisodes() as $episode) {
                    $episodes[] = $episode;
                }
            }
        }

        return $this->aired($episodes);
    }

    private function isAired(Episode $episode): bool
    {
        return $episode->getAirDate() !== null && $episode->getAirDate() <= new \DateTimeImmutable('today');
    }
}
