<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_season_number', columns: ['series_id', 'number'])]
class Season
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'seasons')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Series $series;
    #[ORM\Column] private int $number;
    #[ORM\Column(length: 255, nullable: true)] private ?string $name = null;
    #[ORM\Column(length: 500, nullable: true)] private ?string $coverUrl = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $premieredAt = null;
    /** @var Collection<int, Episode> */ #[ORM\OneToMany(targetEntity: Episode::class, mappedBy: 'season', cascade: ['persist'], orphanRemoval: true)] #[ORM\OrderBy(['number' => 'ASC'])] private Collection $episodes;

    public function __construct(int $number)
    {
        $this->number = $number;
        $this->episodes = new ArrayCollection();
    }

    public function setSeries(Series $series): void
    {
        $this->series = $series;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setCoverUrl(?string $value): void
    {
        $this->coverUrl = $value;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setPremieredAt(?\DateTimeImmutable $value): void
    {
        $this->premieredAt = $value;
    }

    public function getPremieredAt(): ?\DateTimeImmutable
    {
        return $this->premieredAt;
    }

    /** @return Collection<int,Episode> */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function addEpisode(Episode $episode): void
    {
        if (!$this->episodes->contains($episode)) {
            $this->episodes->add($episode);
            $episode->setSeason($this);
        }
    }
}
