<?php

namespace App\Tests\Unit;

use App\Catalog\TvMazeCatalogProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TvMazeCatalogProviderTest extends TestCase
{
    public function testItNormalizesSearchResults(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 42, 'name' => 'Exemple', 'summary' => '<p>Résumé</p>', 'image' => ['medium' => 'https://image.test/show.jpg'], 'premiered' => '2024-01-01', 'weight' => 87, 'rating' => ['average' => 8.4]]]], JSON_THROW_ON_ERROR)));
        $results = (new TvMazeCatalogProvider($client))->search('Exemple');

        self::assertSame([['id' => '42', 'title' => 'Exemple', 'summary' => 'Résumé', 'posterUrl' => 'https://image.test/show.jpg', 'premiered' => '2024-01-01', 'weight' => 87.0, 'rating' => 8.4]], $results);
    }

    public function testItFetchesTheCastEmbed(): void
    {
        $requestedUrl = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse { $requestedUrl = $url; return new MockResponse(json_encode(['id' => 169, '_embedded' => ['cast' => []]], JSON_THROW_ON_ERROR)); });
        $payload = (new TvMazeCatalogProvider($client))->rawCast('169');

        self::assertSame(169, $payload['id']);
        self::assertStringContainsString('/shows/169', (string) $requestedUrl);
        self::assertStringContainsString('embed=cast', (string) $requestedUrl);
    }
}
