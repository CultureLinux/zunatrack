<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Episode;
use App\Entity\LibraryEntry;
use App\Entity\PushSubscription;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use App\Service\EpisodeNotificationScheduler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EpisodeNotificationTest extends WebTestCase
{
    public function testItQueuesOnlyNewlyAiredEpisodesForEligibleLibraryEntries(): void
    {
        self::ensureKernelShutdown();
        static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['push_delivery', 'push_subscription', 'episode_progress', 'library_entry', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }$user = new User()->setEmail('watcher@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'a secure password'));
        $series = new Series()->setTitle('Show');
        $season = new Season(1);
        $episode = (new Episode(1));
        $episode->setTitle('New episode');
        $episode->setAiredAt(new \DateTimeImmutable('2026-11-04 10:00:00+00:00'));
        $season->addEpisode($episode);
        $series->addSeason($season);
        $em->persist($user);
        $em->persist($series);
        $em->flush();
        $em->persist(new LibraryEntry($user, $series));
        $em->persist(PushSubscription::forUser($user, 'https://push.example.test/scheduler', 'key', 'auth', new \DateTimeImmutable('2026-11-04 09:00:00+00:00')));
        $em->flush();
        $scheduler = self::getContainer()->get(EpisodeNotificationScheduler::class);
        self::assertSame(1, $scheduler->queueEligible(new \DateTimeImmutable('2026-11-04 11:00:00+00:00')));
        self::assertSame(0, $scheduler->queueEligible(new \DateTimeImmutable('2026-11-04 11:00:00+00:00')));
    }
}
