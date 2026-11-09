<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InstanceConfiguration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InstanceConfigurationService
{
    public function __construct(private EntityManagerInterface $em, private ConfigurationCipher $cipher)
    {
    }

    public function current(): InstanceConfiguration
    {
        $configuration = $this->em->find(InstanceConfiguration::class, 1);
        if (!$configuration instanceof InstanceConfiguration) {
            $configuration = InstanceConfiguration::defaults();
            $this->em->persist($configuration);
            $this->em->flush();
        }

        return $configuration;
    }

    public function updateRegistration(bool $allowed, ?int $limit): void
    {
        $this->current()->updateRegistration($allowed, $limit);
        $this->em->flush();
    }

    public function updateVapid(string $subject, string $publicKey, string $privateKey): void
    {
        if (!filter_var($subject, FILTER_VALIDATE_EMAIL) && !str_starts_with($subject, 'mailto:')) {
            throw new \InvalidArgumentException('Le sujet VAPID doit être une adresse e-mail ou mailto:.');
        } if ('' === trim($publicKey) || '' === trim($privateKey)) {
            throw new \InvalidArgumentException('Les deux clés VAPID sont requises.');
        } $this->current()->configureVapid(trim($subject), trim($publicKey), $this->cipher->encrypt(trim($privateKey)));
        $this->em->flush();
    }

    public function publicVapidKey(): string
    {
        return $this->current()->getVapidPublicKey() ?? '';
    }

    public function canRegister(): bool
    {
        $configuration = $this->current();

        return $configuration->isPublicRegistration() && (null === $configuration->getAccountLimit() || $this->em->getRepository(User::class)->count([]) < $configuration->getAccountLimit());
    }

    public function privateVapidKey(): ?string
    {
        $value = $this->current()->getEncryptedVapidPrivateKey();

        return null === $value ? null : $this->cipher->decrypt($value);
    }
}
