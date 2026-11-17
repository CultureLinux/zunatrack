<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\CatalogMetrics;
use App\Catalog\CatalogService;
use App\Catalog\PosterCache;
use App\Catalog\TvMazeCatalogProvider;
use App\Entity\CatalogSource;
use App\Entity\Episode;
use App\Entity\LibraryEntry;
use App\Entity\PushSubscription;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use App\Repository\CatalogSourceRepository;
use App\Service\EpisodeNotificationScheduler;
use App\Service\EpisodePushPayload;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBus;
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

    public function testAiringRefreshesImagesBeforeNotificationsAndSharesTheFetchAcrossDevices(): void
    {
        $this->checkAiringImages(false);
    }

    public function testProviderFailureStillQueuesNotificationsWithThePoster(): void
    {
        $this->checkAiringImages(true);
    }

    public function testFreshPostAiringSnapshotDoesNotPollTheProviderEveryMinute(): void
    {
        $this->checkAiringImages(false, true);
    }

    public function testMissingProviderImageUsesThePosterWithoutRepeatedFetches(): void
    {
        $this->checkAiringImages(false, false, true);
    }

    private function checkAiringImages(bool $providerFails, bool $fetchedAfterAiring = false, bool $missingProviderImage = false): void
    {
        self::ensureKernelShutdown();
        static::createClient();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        foreach (['push_delivery', 'push_subscription', 'episode_progress', 'library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $now = new \DateTimeImmutable();
        $aired = $now->modify('-1 hour');
        $user = new User()->setEmail('image-watcher@example.test')->setPassword('unused');
        $series = new Series()->setTitle('Image show')->setPosterUrl('/media/posters/fallback.jpg');
        $season = new Season(1);
        $olderEpisode = new Episode(2);
        $olderEpisode->setTitle('Older episode without artwork');
        $olderEpisode->setAiredAt($now->modify('-1 day'));
        $season->addEpisode($olderEpisode);
        $episode = new Episode(1);
        $episode->setTitle('New image');
        $episode->setAiredAt($aired);
        $season->addEpisode($episode);
        $series->addSeason($season);
        $payload = ['name' => 'Image show', '_embedded' => [
            'cast' => [], 'seasons' => [['number' => 1]],
            'episodes' => [['number' => 1, 'season' => 1, 'name' => 'New image', 'airstamp' => $aired->format(DATE_ATOM), 'image' => null]],
        ]];
        $source = new CatalogSource('tvmaze', '999123', $series);
        $source->refresh($payload, $now->modify('+23 hours'));
        if (!$fetchedAfterAiring) {
            new \ReflectionProperty($source, 'fetchedAt')->setValue($source, $now->modify('-2 hours'));
        }
        foreach ([$user, $series, $source] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->persist(new LibraryEntry($user, $series));
        foreach (['desktop', 'laptop'] as $device) {
            $em->persist(PushSubscription::forUser($user, 'https://push.example.test/'.$device, 'key', 'auth', $now->modify('-3 hours')));
        }
        $em->flush();
        if (!$missingProviderImage) {
            $payload['_embedded']['episodes'][0]['image'] = ['medium' => 'https://static.tvmaze.com/new-episode.jpg'];
        }
        $http = new MockHttpClient(new MockResponse(
            json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => $providerFails ? 503 : 200],
        ));
        $metrics = $container->get(CatalogMetrics::class);
        $imageDir = sys_get_temp_dir().'/zunatrack-airing-'.uniqid();
        $posters = new PosterCache(new MockHttpClient(new MockResponse('image', ['response_headers' => ['content-type: image/jpeg']])), $imageDir, $metrics);
        $catalog = new CatalogService(new TvMazeCatalogProvider($http), $container->get('cache.app'), $container->get(CatalogSourceRepository::class), $em, $container->get('limiter.tvmaze_api'), $posters, $metrics);
        $scheduler = new EpisodeNotificationScheduler($em, new MessageBus(), $catalog, new NullLogger());

        self::assertSame(2, $scheduler->queueEligible($now));
        $image = EpisodePushPayload::forEpisode($episode)['image'];
        if ($providerFails || $fetchedAfterAiring || $missingProviderImage) {
            self::assertSame('/media/posters/fallback.jpg', $image);
        } else {
            self::assertNotNull($episode->getCoverUrl());
            self::assertSame($episode->getCoverUrl(), $image);
            self::assertFileExists($imageDir.'/public'.$image);
        }
        self::assertSame($fetchedAfterAiring ? 0 : 1, $http->getRequestsCount());
        self::assertSame(0, $scheduler->queueEligible($now));
        if (!$providerFails) {
            self::assertSame($fetchedAfterAiring ? 0 : 1, $http->getRequestsCount());
        }
    }
}
