<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ConfigurationCipher;
use PHPUnit\Framework\TestCase;

final class ConfigurationCipherTest extends TestCase
{
    public function testItEncryptsAndDecryptsPrivateConfiguration(): void
    {
        $cipher = new ConfigurationCipher('instance-secret-used-only-for-tests');

        $encrypted = $cipher->encrypt('vapid-private-key');

        self::assertNotSame('vapid-private-key', $encrypted);
        self::assertSame('vapid-private-key', $cipher->decrypt($encrypted));
    }

    public function testItRejectsAlteredCiphertext(): void
    {
        $cipher = new ConfigurationCipher('instance-secret-used-only-for-tests');
        $encrypted = $cipher->encrypt('vapid-private-key');

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt(substr($encrypted, 0, -2).'aa');
    }
}
