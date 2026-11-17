<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalog\CatalogService;
use App\Entity\EpisodeProgress;
use App\Entity\LibraryEntry;
use App\Entity\PushDelivery;
use App\Entity\PushSubscription;
use App\Message\DeliverEpisodeNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class EpisodeNotificationScheduler
{
    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus, private CatalogService $catalog, private LoggerInterface $logger)
    {
    }

    public function queueEligible(\DateTimeImmutable $now): int
    {
        $count = 0;
        $refreshedSeasons = [];
        foreach ($this->em->getRepository(LibraryEntry::class)->findAll() as $entry) {
            if (\in_array($entry->getStatus(), [LibraryEntry::STATUS_PAUSED, LibraryEntry::STATUS_DROPPED], true)) {
                continue;
            }
            foreach ($entry->getSeries()->getSeasons() as $season) {
                foreach ($season->getEpisodes() as $episode) {
                    $aired = $episode->getAiredAt();
                    if (!$aired || $aired > $now || $this->em->getRepository(EpisodeProgress::class)->findOneBy(['user' => $entry->getUser(), 'episode' => $episode])) {
                        continue;
                    }
                    $seriesKey = (string) $entry->getSeries()->getId();
                    $seasonKey = $seriesKey.':'.$season->getNumber();
                    if ($episode->getCoverUrl() === null && !isset($refreshedSeasons[$seasonKey])) {
                        $refreshedSeasons[$seasonKey] = true;
                        $latestEpisode = $episode;
                        foreach ($season->getEpisodes() as $candidate) {
                            $candidateAiredAt = $candidate->getAiredAt();
                            if ($candidate->getCoverUrl() === null && $candidateAiredAt !== null && $candidateAiredAt <= $now && $candidateAiredAt > $latestEpisode->getAiredAt()) {
                                $latestEpisode = $candidate;
                            }
                        }
                        try {
                            $this->catalog->refreshAiredEpisodeImages($latestEpisode);
                        } catch (\Throwable $error) {
                            $this->logger->warning('Rafraîchissement des images TVmaze impossible.', ['series_id' => $seriesKey, 'season' => $season->getNumber(), 'exception_type' => $error::class]);
                        }
                        $aired = $episode->getAiredAt();
                        if (!$aired || $aired > $now) {
                            continue;
                        }
                    }
                    foreach ($this->em->getRepository(PushSubscription::class)->findBy(['user' => $entry->getUser()]) as $subscription) {
                        $alreadyQueued = $this->em->getRepository(PushDelivery::class)->findOneBy(['episode' => $episode, 'subscription' => $subscription]);
                        if ($aired < $subscription->getActivatedAt() || $alreadyQueued) {
                            continue;
                        }
                        $delivery = new PushDelivery($episode, $subscription);
                        $this->em->persist($delivery);
                        $this->em->flush();
                        $this->bus->dispatch(new DeliverEpisodeNotification((int) $delivery->getId()));
                        ++$count;
                    }
                }
            }
        }

        return $count;
    }
}
