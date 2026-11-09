<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PosterCache
{
    private const MAX_BYTES = 5_000_000;

    public function __construct(private HttpClientInterface $client, private string $projectDir, private CatalogMetrics $metrics)
    {
    }

    public function store(?string $url): ?string
    {
        if (!$url || !$this->allowed($url)) {
            return null;
        }$path = parse_url($url, PHP_URL_PATH);
        $ext = \in_array(strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) ? strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) : 'jpg';
        $name = hash('sha256', $url).'.'.$ext;
        $dir = $this->projectDir.'/public/media/posters';
        if (is_file($dir.'/'.$name)) {
            $this->metrics->hit('local');

            return '/media/posters/'.$name;
        }if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }try {
            $response = $this->client->request('GET', $url, ['timeout' => 8, 'max_duration' => 10]);
            $headers = $response->getHeaders(false);
            $type = strtolower($headers['content-type'][0] ?? '');
            if (!preg_match('#^image/(jpeg|png|webp)(;|$)#', $type)) {
                return null;
            }$body = $response->getContent();
            if (\strlen($body) > self::MAX_BYTES) {
                return null;
            }file_put_contents($dir.'/'.$name, $body);
            $this->metrics->hit('provider');

            return '/media/posters/'.$name;
        } catch (\Throwable) {
            return null;
        }
    }

    private function allowed(string $url): bool
    {
        $parts = parse_url($url);

        return \is_array($parts) && ($parts['scheme'] ?? null) === 'https' && ($parts['host'] ?? null) === 'static.tvmaze.com';
    }
}
