<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\CatalogMetrics;
use App\Catalog\CatalogService;
use App\Catalog\PosterCache;
use App\Catalog\TvMazeCatalogProvider;
use App\Entity\CatalogSource;
use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Series;
use App\Repository\CatalogSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CatalogImagesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement("DELETE FROM catalog_source WHERE external_id IN ('repair-images', 'keep-poster', 'incomplete-images')");
        $em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAdminRepairRestoresMissingFilesEvenWhenEpisodeHasACoverUrl(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = new Series()->setTitle('Repair images');
        $season = new Season(1);
        $episode = new Episode(1);
        $episode->setTitle('Pilot');
        $episode->setCoverUrl('/media/posters/'.hash('sha256', 'https://static.tvmaze.com/repair-episode.jpg').'.jpg');
        $season->addEpisode($episode);
        $series->addSeason($season);
        $source = new CatalogSource('tvmaze', 'repair-images', $series);
        $source->refresh(['name' => 'Repair images', 'image' => ['medium' => 'https://static.tvmaze.com/repair-series.jpg'], '_embedded' => [
            'cast' => [], 'seasons' => [['number' => 1]],
            'episodes' => [['season' => 1, 'number' => 1, 'name' => 'Pilot', 'image' => ['medium' => 'https://static.tvmaze.com/repair-episode.jpg']]],
        ]], new \DateTimeImmutable('+1 day'));
        $em->persist($series);
        $em->persist($source);
        $em->flush();
        $imageDir = sys_get_temp_dir().'/zunatrack-repair-images-'.uniqid();
        $service = $this->catalog(new MockHttpClient([]), new MockHttpClient(fn () => new MockResponse('image', ['response_headers' => ['content-type: image/jpeg']])), $imageDir);

        $service->repair($series);

        self::assertFileExists($imageDir.'/public'.$episode->getCoverUrl());
        self::assertFileExists($imageDir.'/public'.$series->getPosterUrl());
    }

    public function testSearchKeepsTheExistingPosterWhenTheNewImageDownloadFails(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = new Series()->setTitle('Keep poster')->setPosterUrl('/media/posters/kept.jpg');
        $source = new CatalogSource('tvmaze', 'keep-poster', $series);
        $em->persist($series);
        $em->persist($source);
        $em->flush();
        $http = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 'keep-poster', 'name' => 'Keep poster', 'image' => ['medium' => 'https://static.tvmaze.com/unavailable.jpg']]]], JSON_THROW_ON_ERROR)));
        $service = $this->catalog($http, new MockHttpClient(new MockResponse('', ['http_code' => 503])), sys_get_temp_dir().'/zunatrack-search-images-'.uniqid());

        $results = $service->search('keep-poster-'.uniqid());

        self::assertSame('/media/posters/kept.jpg', $series->getPosterUrl());
        self::assertSame('/media/posters/kept.jpg', $results[0]['posterUrl']);
    }

    public function testAdminRepairRefetchesAFreshButIncompleteSnapshot(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = new Series()->setTitle('Incomplete snapshot');
        $source = new CatalogSource('tvmaze', 'incomplete-images', $series);
        $source->refresh(['name' => 'Incomplete snapshot', '_embedded' => ['cast' => []]], new \DateTimeImmutable('+1 day'));
        $em->persist($series);
        $em->persist($source);
        $em->flush();
        $http = new MockHttpClient(new MockResponse(json_encode(['name' => 'Incomplete snapshot', '_embedded' => ['cast' => [], 'seasons' => [['number' => 1]], 'episodes' => [['season' => 1, 'number' => 1, 'name' => 'Recovered pilot']]]], JSON_THROW_ON_ERROR)));
        $service = $this->catalog($http, new MockHttpClient([]), sys_get_temp_dir().'/zunatrack-incomplete-images-'.uniqid());

        $service->repair($series);

        self::assertCount(1, $series->getSeasons());
        self::assertSame('Recovered pilot', $series->getSeasons()->first()->getEpisodes()->first()->getTitle());
    }

    public function testNewSearchResultRetainsItsRemotePosterAndRetriesCachingWithoutOpeningTheShow(): void
    {
        $url = 'https://static.tvmaze.com/new-search-poster.jpg';
        $http = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 'new-search-poster', 'name' => 'New search result', 'image' => ['medium' => $url]]]], JSON_THROW_ON_ERROR)));
        $imageHttp = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('image', ['response_headers' => ['content-type: image/jpeg']]),
        ]);
        $imageDir = sys_get_temp_dir().'/zunatrack-new-search-'.uniqid();
        $service = $this->catalog($http, $imageHttp, $imageDir);
        $query = 'new-search-'.uniqid();

        $first = $service->search($query);
        $source = self::getContainer()->get(CatalogSourceRepository::class)->findByProviderId('tvmaze', 'new-search-poster');

        self::assertSame($url, $first[0]['posterUrl']);
        self::assertNotNull($source);
        self::assertSame($url, $source->getSeries()->getPosterUrl());
        self::assertSame($url, $source->getPayload()['_search']['posterUrl']);
        self::assertArrayNotHasKey('name', $source->getPayload());
        self::assertCount(0, $source->getSeries()->getSeasons());

        $second = $service->search($query);

        self::assertStringStartsWith('/media/posters/', $second[0]['posterUrl']);
        self::assertSame($second[0]['posterUrl'], $source->getSeries()->getPosterUrl());
        self::assertFileExists($imageDir.'/public'.$second[0]['posterUrl']);
        self::assertSame($url, $source->getPayload()['_search']['posterUrl']);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testSearchCanUseTheOriginalPosterWhenTheMediumImageIsAbsent(): void
    {
        $url = 'https://static.tvmaze.com/original-only.jpg';
        $http = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 'original-only', 'name' => 'Original poster', 'image' => ['original' => $url]]]], JSON_THROW_ON_ERROR)));
        $service = $this->catalog($http, new MockHttpClient(new MockResponse('', ['http_code' => 503])), sys_get_temp_dir().'/zunatrack-original-poster-'.uniqid());

        $results = $service->search('original-poster-'.uniqid());

        self::assertSame($url, $results[0]['posterUrl']);
    }

    public function testAllNewResultsAreStoredWhenOnePosterDownloadFails(): void
    {
        $rows = [
            ['show' => ['id' => 'batch-cached', 'name' => 'Cached result', 'image' => ['medium' => 'https://static.tvmaze.com/batch-cached.jpg']]],
            ['show' => ['id' => 'batch-remote', 'name' => 'Remote result', 'image' => ['medium' => 'https://static.tvmaze.com/batch-remote.jpg']]],
            ['show' => ['id' => 'batch-no-image', 'name' => 'No image result', 'image' => null]],
        ];
        $http = new MockHttpClient(new MockResponse(json_encode($rows, JSON_THROW_ON_ERROR)));
        $images = new MockHttpClient(fn (string $method, string $url) => str_ends_with($url, 'batch-remote.jpg')
            ? new MockResponse('', ['http_code' => 503])
            : new MockResponse('image', ['response_headers' => ['content-type: image/jpeg']]));
        $imageDir = sys_get_temp_dir().'/zunatrack-batch-search-'.uniqid();
        $service = $this->catalog($http, $images, $imageDir);

        $results = $service->search('batch-search-'.uniqid());

        self::assertCount(3, $results);
        self::assertStringStartsWith('/media/posters/', $results[0]['posterUrl']);
        self::assertFileExists($imageDir.'/public'.$results[0]['posterUrl']);
        self::assertSame('https://static.tvmaze.com/batch-remote.jpg', $results[1]['posterUrl']);
        self::assertNull($results[2]['posterUrl']);
        foreach ($results as $result) {
            $source = self::getContainer()->get(CatalogSourceRepository::class)->findByProviderId('tvmaze', $result['id']);
            self::assertNotNull($source);
            self::assertSame($result['posterUrl'], $source->getSeries()->getPosterUrl());
            self::assertCount(0, $source->getSeries()->getSeasons());
        }
    }

    public function testSearchNeverUsesAnUntrustedRemotePosterAsFallback(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 'untrusted-poster', 'name' => 'Untrusted poster', 'image' => ['medium' => 'https://example.test/poster.jpg']]]], JSON_THROW_ON_ERROR)));
        $service = $this->catalog($http, new MockHttpClient([]), sys_get_temp_dir().'/zunatrack-untrusted-poster-'.uniqid());

        $results = $service->search('untrusted-poster-'.uniqid());

        self::assertNull($results[0]['posterUrl']);
    }

    private function catalog(MockHttpClient $providerHttp, MockHttpClient $imageHttp, string $imageDir): CatalogService
    {
        $container = self::getContainer();
        $metrics = $container->get(CatalogMetrics::class);

        return new CatalogService(new TvMazeCatalogProvider($providerHttp), $container->get('cache.app'), $container->get(CatalogSourceRepository::class), $container->get(EntityManagerInterface::class), $container->get('limiter.tvmaze_api'), new PosterCache($imageHttp, $imageDir, $metrics), $metrics);
    }
}
