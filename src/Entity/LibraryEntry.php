<?php

namespace App\Entity;

use App\Repository\LibraryEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryEntryRepository::class)]
#[ORM\Table(name: 'library_entry')]
#[ORM\UniqueConstraint(name: 'uniq_library_user_series', columns: ['user_id', 'series_id'])]
class LibraryEntry
{
    public const STATUS_WATCHLIST = 'watchlist';
    public const STATUS_WATCHING = 'watching';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DROPPED = 'dropped';
    public const STATUS_PAUSED = 'paused';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_WATCHLIST => 'À voir', self::STATUS_WATCHING => 'En cours',
        self::STATUS_COMPLETED => 'Terminée', self::STATUS_DROPPED => 'Abandonnée', self::STATUS_PAUSED => 'En pause',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Series $series;
    #[ORM\Column(length: 16)] private string $status = self::STATUS_WATCHLIST;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $note = null;
    #[ORM\Column] private \DateTimeImmutable $addedAt;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $startedAt = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $finishedAt = null;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, Series $series) { $this->user = $user; $this->series = $series; $this->addedAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSeries(): Series { return $this->series; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { if (!isset(self::STATUSES[$status])) { throw new \InvalidArgumentException('Statut de bibliothèque invalide.'); } $this->status = $status; $this->touch(); }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): void { $this->note = $note === null || trim($note) === '' ? null : trim($note); $this->touch(); }
    public function getAddedAt(): \DateTimeImmutable { return $this->addedAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $date): void { $this->startedAt = $date; $this->touch(); }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(?\DateTimeImmutable $date): void { $this->finishedAt = $date; $this->touch(); }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
