<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_item')]
#[ORM\UniqueConstraint(name: 'uniq_import_item_batch_external', columns: ['batch_id', 'external_key'])]
class ImportItem
{
    public const STATE_PENDING = 'pending';
    public const STATE_IMPORTED = 'imported';
    public const STATE_MERGED = 'merged';
    public const STATE_UNRESOLVED = 'unresolved';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'items')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ImportBatch $batch;
    #[ORM\Column(length: 80)] private string $externalKey;
    #[ORM\Column(length: 255)] private string $title;
    #[ORM\Column(type: 'json')] private array $payload;
    #[ORM\Column(length: 16)] private string $state = self::STATE_PENDING;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $reason = null;
    #[ORM\Column(type: 'json')] private array $candidates = [];
    #[ORM\Column(length: 500, nullable: true)] private ?string $coverUrl = null;
    #[ORM\Column(length: 64, nullable: true)] private ?string $selectedCandidateId = null;

    /** @param array<string,mixed> $payload */
    public function __construct(ImportBatch $batch, string $externalKey, string $title, array $payload) { $this->batch=$batch; $this->externalKey=$externalKey; $this->title=$title; $this->payload=$payload; $batch->addItem($this); }
    public function getId(): ?int { return $this->id; }
    public function getBatch(): ImportBatch { return $this->batch; }
    public function getExternalKey(): string { return $this->externalKey; }
    public function getTitle(): string { return $this->title; }
    /** @return array<string,mixed> */ public function getPayload(): array { return $this->payload; }
    public function getState(): string { return $this->state; }
    public function getReason(): ?string { return $this->reason; }
    /** @return list<array{id:string,title:string}> */ public function getCandidates(): array { return $this->candidates; }
    public function getCoverUrl(): ?string { return $this->coverUrl; }
    public function getSelectedCandidateId(): ?string { return $this->selectedCandidateId; }
    /** @param list<array{id:string,title:string}> $candidates */ public function unresolved(string $reason, array $candidates=[]): void { $this->state=self::STATE_UNRESOLVED; $this->reason=$reason; $this->candidates=$candidates; }
    public function complete(string $state, ?string $coverUrl): void { $this->state=$state; $this->coverUrl=$coverUrl; $this->reason=null; $this->candidates=[]; }
    public function replay(string $candidateId): void { $this->selectedCandidateId=$candidateId; $this->state=self::STATE_PENDING; $this->reason=null; }
}
