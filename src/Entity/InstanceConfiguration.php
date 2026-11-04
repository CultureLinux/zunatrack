<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'instance_configuration')]
final class InstanceConfiguration
{
    #[ORM\Id] #[ORM\Column] private int $id = 1;
    #[ORM\Column] private bool $publicRegistration = true;
    #[ORM\Column(nullable: true)] private ?int $accountLimit = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $vapidSubject = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $vapidPublicKey = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $encryptedVapidPrivateKey = null;

    public static function defaults(): self { return new self(); }
    public function isPublicRegistration(): bool { return $this->publicRegistration; }
    public function getAccountLimit(): ?int { return $this->accountLimit; }
    public function getVapidSubject(): ?string { return $this->vapidSubject; }
    public function getVapidPublicKey(): ?string { return $this->vapidPublicKey; }
    public function getEncryptedVapidPrivateKey(): ?string { return $this->encryptedVapidPrivateKey; }
    public function updateRegistration(bool $allowed, ?int $limit): void { if (null !== $limit && $limit < 1) throw new \InvalidArgumentException('La limite doit être un entier positif.'); $this->publicRegistration = $allowed; $this->accountLimit = $limit; }
    public function configureVapid(string $subject, string $publicKey, string $encryptedPrivateKey): void { $this->vapidSubject = $subject; $this->vapidPublicKey = $publicKey; $this->encryptedVapidPrivateKey = $encryptedPrivateKey; }
}
