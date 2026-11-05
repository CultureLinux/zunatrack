<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ParsedImport
{
    /** @param list<ImportPayload> $items */
    public function __construct(public string $format, public array $items) {}
}
