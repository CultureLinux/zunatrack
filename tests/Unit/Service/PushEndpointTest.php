<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PushEndpoint;
use PHPUnit\Framework\TestCase;

final class PushEndpointTest extends TestCase
{
    public function testAllowsKnownWebPushProvidersOnly(): void
    {
        self::assertTrue(PushEndpoint::isAllowed('https://fcm.googleapis.com/fcm/send/token'));
        self::assertTrue(PushEndpoint::isAllowed('https://updates.push.services.mozilla.com/wpush/v2/token'));
        self::assertFalse(PushEndpoint::isAllowed('https://example.test/push'));
        self::assertFalse(PushEndpoint::isAllowed('http://fcm.googleapis.com/fcm/send/token'));
        self::assertFalse(PushEndpoint::isAllowed('https://user@fcm.googleapis.com/fcm/send/token'));
        self::assertFalse(PushEndpoint::isAllowed('https://fcm.googleapis.com:8443/fcm/send/token'));
        self::assertFalse(PushEndpoint::isAllowed('https://fcm.googleapis.com/fcm/send/token#fragment'));
    }
}
