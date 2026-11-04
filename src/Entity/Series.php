<?php

namespace App\Entity;

use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeriesRepository::class)]
class Series
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 255)] private string $title = '';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $summary = null;
    #[ORM\Column(length: 500, nullable: true)] private ?string $posterUrl = null;
    #[ORM\Column(length: 40, nullable: true)] private ?string $status = null;
    #[ORM\Column(type: 'json')] private array $genres = [];
    /** @var list<array{name:string,character:?string,imageUrl:?string}> */ #[ORM\Column(type: 'json')] private array $cast = [];
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $premieredAt = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $endedAt = null;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, Season> */ #[ORM\OneToMany(targetEntity: Season::class, mappedBy: 'series', cascade: ['persist'], orphanRemoval: true)] #[ORM\OrderBy(['number' => 'ASC'])] private Collection $seasons;

    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); $this->seasons = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $value): static { $this->title = $value; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $value): static { $this->summary = $value; return $this; }
    public function getPosterUrl(): ?string { return $this->posterUrl; }
    public function setPosterUrl(?string $value): static { $this->posterUrl = $value; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $value): static { $this->status = $value; return $this; }
    public function getGenres(): array { return $this->genres; }
    public function setGenres(array $value): static { $this->genres = $value; return $this; }
    /** @return list<array{name:string,character:?string,imageUrl:?string}> */ public function getCast(): array { return $this->cast; }
    /** @param list<array{name:string,character:?string,imageUrl:?string}> $value */ public function setCast(array $value): static { $this->cast = $value; return $this; }
    public function getPremieredAt(): ?\DateTimeImmutable { return $this->premieredAt; }
    public function setPremieredAt(?\DateTimeImmutable $value): static { $this->premieredAt = $value; return $this; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setEndedAt(?\DateTimeImmutable $value): static { $this->endedAt = $value; return $this; }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    /** @return Collection<int, Season> */ public function getSeasons(): Collection { return $this->seasons; }
    public function addSeason(Season $season): void { if (!$this->seasons->contains($season)) { $this->seasons->add($season); $season->setSeries($this); } }
}
