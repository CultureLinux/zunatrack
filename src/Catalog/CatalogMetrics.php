<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CatalogMetrics
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function hit(string $kind): void
    {
        $values = $this->totals();
        $values[$kind] = ($values[$kind] ?? 0) + 1;
        $now = new \DateTimeImmutable();
        $day = $now->format('Y-m-d');
        $values['history'][$day][$kind] = ($values['history'][$day][$kind] ?? 0) + 1;
        $this->cache->delete('catalog.metrics');
        $this->cache->get('catalog.metrics', function (ItemInterface $item) use ($values) {
            $item->expiresAfter(31536000);

            return $values;
        });
    }

    public function totals(): array
    {
        return $this->cache->get('catalog.metrics', fn (ItemInterface $item) => ['provider' => 0, 'local' => 0, 'history' => []]);
    }
}
