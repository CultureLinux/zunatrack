<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessImportItem
{
    public function __construct(public int $itemId)
    {
    }
}
