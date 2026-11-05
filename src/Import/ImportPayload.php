<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ImportPayload
{
    /** @param list<int> $seasons @param array<int, list<int>> $watchedEpisodes @param list<list<string>> $yamtrackRows */
    public function __construct(
        public string $externalKey,
        public string $title,
        public ?string $status,
        public ?string $notes,
        public ?string $startedAt,
        public ?string $finishedAt,
        public array $seasons,
        public array $watchedEpisodes,
        public array $yamtrackRows = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array { return ['externalKey'=>$this->externalKey,'title'=>$this->title,'status'=>$this->status,'notes'=>$this->notes,'startedAt'=>$this->startedAt,'finishedAt'=>$this->finishedAt,'seasons'=>$this->seasons,'watchedEpisodes'=>$this->watchedEpisodes,'yamtrackRows'=>$this->yamtrackRows]; }
    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self { return new self((string)$data['externalKey'],(string)$data['title'],$data['status']??null,$data['notes']??null,$data['startedAt']??null,$data['finishedAt']??null,$data['seasons']??[],$data['watchedEpisodes']??[],$data['yamtrackRows']??[]); }
}
