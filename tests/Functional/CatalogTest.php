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
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CatalogTest extends WebTestCase
{
    public function testCatalogImportIsNotAvailableViaGet(): void
    {
        $client = static::createClient();
        $user = new User()->setEmail('import-method@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        self::getContainer()->get(EntityManagerInterface::class)->persist($user);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($user);
        $client->request('GET', '/catalogue/tvmaze/1');
        self::assertResponseStatusCodeSame(405);
    }

    public function testSearchStoresALightweightLocalSeriesWithoutSeasonsOrEpisodes(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $provider = new TvMazeCatalogProvider(new MockHttpClient([
            new MockResponse(json_encode([['show' => ['id' => 420, 'name' => 'Stored show', 'summary' => '<p>Stored summary</p>', 'premiered' => '2024-01-01', 'weight' => 90, 'rating' => ['average' => 8.8]]]], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['id' => 420, 'name' => 'Stored show', 'image' => ['medium' => 'https://static.tvmaze.com/show.jpg'], '_embedded' => ['cast' => [], 'seasons' => [['number' => 1, 'name' => 'Season one', 'image' => null]], 'episodes' => []]], JSON_THROW_ON_ERROR)),
        ]));
        $posters = new PosterCache(new MockHttpClient(new MockResponse('image', ['response_headers' => ['content-type: image/jpeg']])), sys_get_temp_dir().'/zunatrack-catalog-test-'.uniqid(), self::getContainer()->get(CatalogMetrics::class));
        $service = new CatalogService($provider, self::getContainer()->get('cache.app'), self::getContainer()->get(\App\Repository\CatalogSourceRepository::class), $em, self::getContainer()->get('limiter.tvmaze_api'), $posters, self::getContainer()->get(CatalogMetrics::class));

        $service->search('stored-'.uniqid('', true));

        $source = $em->getRepository(CatalogSource::class)->findOneBy(['externalId' => '420']);
        self::assertInstanceOf(CatalogSource::class, $source);
        self::assertSame('Stored show', $source->getSeries()->getTitle());
        self::assertSame(90.0, $source->getSeries()->getPopularity());
        self::assertSame(8.8, $source->getSeries()->getRating());
        self::assertCount(0, $source->getSeries()->getSeasons());

        $source->refresh(['_embedded' => ['cast' => []]], new \DateTimeImmutable('+24 hours'));
        $em->flush();
        $service->enrichFromSnapshot($source->getSeries());
        self::assertSame('Stored show', $source->getSeries()->getTitle());
        self::assertCount(1, $source->getSeries()->getSeasons());
        self::assertSame('/media/posters/'.hash('sha256', 'https://static.tvmaze.com/show.jpg').'.jpg', $source->getSeries()->getSeasons()->first()->getCoverUrl());
    }

    public function testEmptySearchSuggestsLocalSeriesOutsideTheUsersLibrary(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }

        $user = new User()->setEmail('catalog@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $kept = new Series()->setTitle('À découvrir')->setPosterUrl('/media/posters/discover.jpg');
        $withoutCover = new Series()->setTitle('Sans couverture');
        $hidden = new Series()->setTitle('Déjà dans ma bibliothèque')->setPosterUrl('/media/posters/hidden.jpg');
        $em->persist($user);
        $em->persist($kept);
        $em->persist($withoutCover);
        $em->persist($hidden);
        $em->flush();
        $em->persist(new LibraryEntry($user, $hidden));
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/catalogue');

        self::assertSelectorExists('.catalog-intro > .page-header + .catalog-search');
        self::assertSelectorTextContains('.catalog-suggestions', 'À découvrir');
        self::assertSelectorNotExists('.catalog-suggestions h2');
        self::assertSelectorExists('.catalog-suggestions img[src="/media/posters/discover.jpg"]');
        self::assertSelectorExists('.catalog-suggestions img[src="/images/zunatrack-mark.svg"]');
        self::assertSelectorTextNotContains('.catalog-suggestions', 'Déjà dans ma bibliothèque');
    }

    public function testEmptySearchGroupsTwentyFourOrMoreSuggestionsWithoutDuplicatingTitles(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $user = new User()->setEmail('genres@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $em->persist($user);
        foreach (array_merge(array_fill(0, 10, ['Fantasy']), array_fill(0, 5, ['Fantasy', 'Drama']), [['Drama']], array_fill(0, 4, ['Adventure']), array_fill(0, 4, [])) as $index => $genres) {
            $em->persist(new Series()->setTitle('Suggestion '.$index)->setGenres($genres));
        }
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/catalogue');

        self::assertSelectorCount(3, '.catalog-suggestions');
        self::assertSelectorCount(24, '.catalog-suggestion');
        $groups = $client->getCrawler()->filter('.catalog-suggestions');
        self::assertStringContainsString('Fantasy', $groups->eq(0)->text());
        self::assertStringContainsString('Adventure', $groups->eq(1)->text());
        self::assertStringContainsString('Autres à découvrir', $groups->eq(2)->text());
    }

    public function testAnonymousVisitorCanOpenAnImportedSeasonWithoutProgressData(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $series = new Series()->setTitle('Seasonal show');
        $season = new Season(1);
        $season->setCoverUrl('/media/posters/season.jpg');
        $episode = new Episode(1);
        $episode->setTitle('Pilot');
        $season->addEpisode($episode);
        $series->addSeason($season);
        $user = new User()->setEmail('viewer@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $em->persist($series);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/series/'.$series->getId().'?saison=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.series-poster[src="/media/posters/season.jpg"]');
    }

    public function testCatalogMarksACompletedLibrarySeriesAsSeen(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['episode_progress', 'library_entry', 'catalog_source', 'series', 'user'] as $table) {
            $em->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $user = new User()->setEmail('seen@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $series = new Series()->setTitle('Seen show')->setCast([['name' => 'Ava Viewer', 'character' => null, 'imageUrl' => null]]);
        $season = new Season(1);
        $episode = new Episode(1);
        $episode->setTitle('Pilot');
        $episode->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $season->addEpisode($episode);
        $series->addSeason($season);
        $em->persist($user);
        $em->persist($series);
        $em->flush();
        $em->persist(new LibraryEntry($user, $series));
        $em->persist(new \App\Entity\EpisodeProgress($user, $episode));
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/catalogue?acteur=Ava%20Viewer');

        self::assertSelectorExists('.result-card .catalog-seen[aria-label="Série entièrement vue"]');
    }
}
