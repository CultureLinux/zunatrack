<?php

declare(strict_types=1);

namespace App\Catalog;

interface SeriesCatalogProvider
{
    public function name(): string;

    /** @return list<array{id:string,title:string,summary:?string,posterUrl:?string,premiered:?string}> */
    public function search(string $query): array;

    /** @return array<string,mixed> */
    public function fetch(string $externalId): array;
}
