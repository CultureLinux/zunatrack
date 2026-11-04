<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{EpisodeProgress,LibraryEntry,PushDelivery,PushSubscription};use App\Message\DeliverEpisodeNotification;use Doctrine\ORM\EntityManagerInterface;use Symfony\Component\Messenger\MessageBusInterface;
final readonly class EpisodeNotificationScheduler
{
    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus) {}

    public function queueEligible(\DateTimeImmutable $now): int
    {
        $count = 0;
        foreach ($this->em->getRepository(LibraryEntry::class)->findAll() as $entry) {
            if (in_array($entry->getStatus(), [LibraryEntry::STATUS_PAUSED, LibraryEntry::STATUS_DROPPED], true)) continue;
            foreach ($entry->getSeries()->getSeasons() as $season) foreach ($season->getEpisodes() as $episode) {
                $aired = $episode->getAiredAt();
                if (!$aired || $aired > $now || $this->em->getRepository(EpisodeProgress::class)->findOneBy(['user' => $entry->getUser(), 'episode' => $episode])) continue;
                foreach ($this->em->getRepository(PushSubscription::class)->findBy(['user' => $entry->getUser()]) as $subscription) {
                    $alreadyQueued = $this->em->getRepository(PushDelivery::class)->findOneBy(['episode' => $episode, 'subscription' => $subscription]);
                    if ($aired < $subscription->getActivatedAt() || $alreadyQueued) continue;
                    $delivery = new PushDelivery($episode, $subscription);
                    $this->em->persist($delivery);
                    $this->em->flush();
                    $this->bus->dispatch(new DeliverEpisodeNotification((int) $delivery->getId()));
                    ++$count;
                }
            }
        }
        return $count;
    }
}
