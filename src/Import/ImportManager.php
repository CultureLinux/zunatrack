<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImportManager
{
    public function __construct(private CsvImportParser $parser, private EntityManagerInterface $em) {}

    public function create(User $user, UploadedFile $file): ImportBatch
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') throw new \InvalidArgumentException('Choisissez un fichier CSV.');
        if ($file->getSize() !== null && $file->getSize() > 10_000_000) throw new \InvalidArgumentException('Le fichier dépasse la limite de 10 Mo.');
        $parsed = $this->parser->parse($file->getPathname());
        $batch = new ImportBatch($user, $parsed->format);
        foreach ($parsed->items as $payload) $this->em->persist(new ImportItem($batch, $payload->externalKey, $payload->title, $payload->toArray()));
        $this->em->persist($batch);
        $this->em->flush();
        return $batch;
    }
}
