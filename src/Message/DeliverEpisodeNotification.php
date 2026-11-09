<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DeliverEpisodeNotification
{
    public function __construct(public int $deliveryId)
    {
    }
}
