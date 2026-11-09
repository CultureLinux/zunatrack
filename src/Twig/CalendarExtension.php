<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\LibraryEntry;
use App\Entity\User;
use App\Repository\EpisodeProgressRepository;
use App\Repository\LibraryEntryRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CalendarExtension extends AbstractExtension
{
    public function __construct(private Security $security, private LibraryEntryRepository $entries, private EpisodeProgressRepository $progress)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('calendar_upcoming_count', $this->count(...)), new TwigFunction('watching_available_count', $this->availableCount(...))];
    }

    public function count(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }
        $today = new \DateTimeImmutable('today');
        $count = 0;
        foreach ($this->entries->findForUser($user) as $entry) {
            if (\in_array($entry->getStatus(), [LibraryEntry::STATUS_COMPLETED, LibraryEntry::STATUS_DROPPED, LibraryEntry::STATUS_PAUSED], true)) {
                continue;
            }
            foreach ($entry->getSeries()->getSeasons() as $season) {
                foreach ($season->getEpisodes() as $episode) {
                    if ($episode->getAirDate() && $episode->getAirDate() >= $today && !$this->progress->findOneBy(['user' => $user, 'episode' => $episode])) {
                        ++$count;
                    }
                }
            }
        }

        return $count;
    }

    public function availableCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }
        $today = new \DateTimeImmutable('today');
        $series = [];
        foreach ($this->entries->findForUser($user) as $entry) {
            if (\in_array($entry->getStatus(), [LibraryEntry::STATUS_COMPLETED, LibraryEntry::STATUS_DROPPED, LibraryEntry::STATUS_PAUSED], true)) {
                continue;
            }
            foreach ($entry->getSeries()->getSeasons() as $season) {
                foreach ($season->getEpisodes() as $episode) {
                    if ($episode->getAirDate() && $episode->getAirDate() <= $today && !$this->progress->findOneBy(['user' => $user, 'episode' => $episode])) {
                        $series[$entry->getSeries()->getId()] = true;
                        break 2;
                    }
                }
            }
        }

        return \count($series);
    }
}
