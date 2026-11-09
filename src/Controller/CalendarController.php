<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LibraryEntry;
use App\Entity\User;
use App\Repository\EpisodeProgressRepository;
use App\Repository\LibraryEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/calendrier', name: 'app_calendar', methods: ['GET'])]
    public function index(LibraryEntryRepository $entries, EpisodeProgressRepository $progress): Response
    {
        return $this->render('calendar/index.html.twig', ['episodes' => $this->upcoming($entries, $progress)]);
    }

    #[Route('/calendrier.ics', name: 'app_calendar_ical', methods: ['GET'])]
    public function ical(LibraryEntryRepository $entries, EpisodeProgressRepository $progress): Response
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ZunaTrack//Calendrier//FR', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:ZunaTrack'];
        foreach ($this->upcoming($entries, $progress) as $item) {
            $episode = $item['episode'];
            $date = $episode->getAiredAt() ?? $episode->getAirDate();
            if (!$date) {
                continue;
            }
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:zunatrack-episode-'.$episode->getId().'@local';
            $lines[] = 'DTSTAMP:'.gmdate('Ymd\\THis\\Z');
            $lines[] = $episode->getAiredAt() ? 'DTSTART:'.$episode->getAiredAt()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z') : 'DTSTART;VALUE=DATE:'.$date->format('Ymd');
            $lines[] = 'SUMMARY:'.$this->escape($item['series']->getTitle().' — S'.str_pad((string) $item['season']->getNumber(), 2, '0', STR_PAD_LEFT).'E'.str_pad((string) $episode->getNumber(), 2, '0', STR_PAD_LEFT).' '.$episode->getTitle());
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return new Response(implode("\r\n", $lines)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="zunatrack.ics"']);
    }

    /** @return list<array{entry: LibraryEntry, series: \App\Entity\Series, season: \App\Entity\Season, episode: \App\Entity\Episode}> */
    private function upcoming(LibraryEntryRepository $entries, EpisodeProgressRepository $progress): array
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $today = new \DateTimeImmutable('today');
        $result = [];
        foreach ($entries->findForUser($user) as $entry) {
            if (\in_array($entry->getStatus(), [LibraryEntry::STATUS_COMPLETED, LibraryEntry::STATUS_DROPPED, LibraryEntry::STATUS_PAUSED], true)) {
                continue;
            }
            foreach ($entry->getSeries()->getSeasons() as $season) {
                foreach ($season->getEpisodes() as $episode) {
                    if (!$episode->getAirDate() || $episode->getAirDate() < $today || $progress->findOneBy(['user' => $user, 'episode' => $episode])) {
                        continue;
                    }
                    $result[] = compact('entry', 'season', 'episode') + ['series' => $entry->getSeries()];
                }
            }
        }
        usort($result, fn (array $a, array $b): int => ($a['episode']->getAiredAt() ?? $a['episode']->getAirDate()) <=> ($b['episode']->getAiredAt() ?? $b['episode']->getAirDate()));

        return $result;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}
