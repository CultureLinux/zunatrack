<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_batch')]
class ImportBatch
{
    public const STATE_PENDING = 'pending';
    public const STATE_RUNNING = 'running';
    public const STATE_DONE = 'done';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user;
    #[ORM\Column(length: 32)] private string $format;
    #[ORM\Column(length: 16)] private string $state = self::STATE_PENDING;
    #[ORM\Column] private int $total = 0;
    #[ORM\Column] private int $processed = 0;
    #[ORM\Column] private int $imported = 0;
    #[ORM\Column] private int $merged = 0;
    #[ORM\Column] private int $unresolved = 0;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    /** @var Collection<int, ImportItem> */
    #[ORM\OneToMany(targetEntity: ImportItem::class, mappedBy: 'batch', cascade: ['persist'], orphanRemoval: true)] private Collection $items;

    public function __construct(User $user, string $format) { $this->user = $user; $this->format = $format; $this->createdAt = new \DateTimeImmutable(); $this->items = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getFormat(): string { return $this->format; }
    public function getState(): string { return $this->state; }
    public function getTotal(): int { return $this->total; }
    public function getProcessed(): int { return $this->processed; }
    public function getImported(): int { return $this->imported; }
    public function getMerged(): int { return $this->merged; }
    public function getUnresolved(): int { return $this->unresolved; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, ImportItem> */ public function getItems(): Collection { return $this->items; }
    public function addItem(ImportItem $item): void { if (!$this->items->contains($item)) { $this->items->add($item); ++$this->total; } }
    public function record(string $state): void { ++$this->processed; if ($state === ImportItem::STATE_IMPORTED) ++$this->imported; if ($state === ImportItem::STATE_MERGED) ++$this->merged; if ($state === ImportItem::STATE_UNRESOLVED) ++$this->unresolved; $this->state = $this->processed >= $this->total ? self::STATE_DONE : self::STATE_RUNNING; }
    public function reopenUnresolved(): void { if($this->unresolved > 0)--$this->unresolved; if($this->processed > 0)--$this->processed; $this->state=self::STATE_RUNNING; }
    public function finishWhenEmpty(): void { if ($this->total === 0) $this->state = self::STATE_DONE; }
}
