<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ConfigurationCipher
{
    private const VERSION = 'v1';

    public function __construct(private string $appSecret) {}

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::VERSION.'.'.base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function decrypt(string $value): string
    {
        [$version, $encoded] = explode('.', $value, 2) + [null, null];
        if (self::VERSION !== $version || !is_string($encoded)) throw new \RuntimeException('La configuration chiffrée est invalide.');
        $payload = base64_decode($encoded, true);
        if (false === $payload || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('La configuration chiffrée est invalide.');
        $plain = sodium_crypto_secretbox_open(substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());
        if (false === $plain) throw new \RuntimeException('La configuration chiffrée est invalide.');
        return $plain;
    }

    private function key(): string { return sodium_crypto_generichash($this->appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES); }
}
