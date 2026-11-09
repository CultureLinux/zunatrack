<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'episode_progress')]
#[ORM\UniqueConstraint(name: 'uniq_episode_progress_user_episode', columns: ['user_id', 'episode_id'])]
class EpisodeProgress
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Episode $episode;
    #[ORM\Column] private \DateTimeImmutable $watchedAt;
    public function __construct(User $user, Episode $episode)
    {
        $this->user = $user;
        $this->episode = $episode;
        $this->watchedAt = new \DateTimeImmutable();
    }

    public function getEpisode(): Episode
    {
        return $this->episode;
    }

    public function getWatchedAt(): \DateTimeImmutable
    {
        return $this->watchedAt;
    }
}
