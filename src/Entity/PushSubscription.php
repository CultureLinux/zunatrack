<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint', columns: ['endpoint_hash'])]
final class PushSubscription
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user;
    #[ORM\Column(type: 'text')] private string $endpoint;
    #[ORM\Column(name: 'endpoint_hash', length: 64)] private string $endpointHash;
    #[ORM\Column(type: 'text')] private string $p256dh;
    #[ORM\Column(type: 'text')] private string $auth;
    #[ORM\Column] private \DateTimeImmutable $activatedAt;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $userAgent = null;
    #[ORM\Column(length: 20, nullable: true)] private ?string $lastResult = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $lastResultAt = null;
    private function __construct(User $user, string $endpoint, string $p256dh, string $auth, \DateTimeImmutable $activatedAt, ?string $userAgent = null)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->p256dh = $p256dh;
        $this->auth = $auth;
        $this->activatedAt = $activatedAt;
        $this->userAgent = $userAgent;
    }

    public static function forUser(User $user, string $endpoint, string $p256dh, string $auth, \DateTimeImmutable $activatedAt, ?string $userAgent = null): self
    {
        return new self($user, $endpoint, $p256dh, $auth, $activatedAt, $userAgent);
    }

    public function refresh(string $p256dh, string $auth, \DateTimeImmutable $activatedAt, ?string $userAgent = null): void
    {
        $this->p256dh = $p256dh;
        $this->auth = $auth;
        $this->activatedAt = $activatedAt;
        $this->userAgent = $userAgent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function getActivatedAt(): \DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function recordResult(string $result): void
    {
        $this->lastResult = $result;
        $this->lastResultAt = new \DateTimeImmutable();
    }

    public function getLastResult(): ?string
    {
        return $this->lastResult;
    }

    public function getLastResultAt(): ?\DateTimeImmutable
    {
        return $this->lastResultAt;
    }
}
