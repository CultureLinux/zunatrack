<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PushSubscription;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PushSubscriptionTest extends TestCase
{
    public function testItTracksActivationAndHashesItsEndpoint(): void
    {
        $activatedAt = new \DateTimeImmutable('2026-11-04 14:00:00+00:00');
        $subscription = PushSubscription::forUser(
            (new User())->setEmail('viewer@example.test'),
            'https://push.example.test/subscriptions/a-secret-endpoint',
            'browser-public-key',
            'browser-auth-secret',
            $activatedAt,
        );

        self::assertSame($activatedAt, $subscription->getActivatedAt());
        self::assertSame(hash('sha256', 'https://push.example.test/subscriptions/a-secret-endpoint'), $subscription->getEndpointHash());
        self::assertSame('https://push.example.test/subscriptions/a-secret-endpoint', $subscription->getEndpoint());
    }
}
