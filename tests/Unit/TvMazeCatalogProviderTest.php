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
        $client = new MockHttpClient(new MockResponse(json_encode([['show' => ['id' => 42, 'name' => 'Exemple', 'summary' => '<p>Résumé</p>', 'image' => ['medium' => 'https://image.test/show.jpg'], 'premiered' => '2024-01-01']]], JSON_THROW_ON_ERROR)));
        $results = (new TvMazeCatalogProvider($client))->search('Exemple');

        self::assertSame([['id' => '42', 'title' => 'Exemple', 'summary' => 'Résumé', 'posterUrl' => 'https://image.test/show.jpg', 'premiered' => '2024-01-01']], $results);
    }
}
