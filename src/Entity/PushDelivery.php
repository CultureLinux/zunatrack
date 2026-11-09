<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_delivery')]
#[ORM\UniqueConstraint(name: 'uniq_push_delivery_episode_subscription', columns: ['episode_id', 'subscription_id'])]
final class PushDelivery
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Episode $episode;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private PushSubscription $subscription;
    #[ORM\Column] private \DateTimeImmutable $queuedAt;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $deliveredAt = null;
    public function __construct(Episode $episode, PushSubscription $subscription)
    {
        $this->episode = $episode;
        $this->subscription = $subscription;
        $this->queuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisode(): Episode
    {
        return $this->episode;
    }

    public function getSubscription(): PushSubscription
    {
        return $this->subscription;
    }

    public function markDelivered(): void
    {
        $this->deliveredAt = new \DateTimeImmutable();
    }
}
