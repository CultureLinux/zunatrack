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
        $keys = array_map(fn(ImportPayload $payload): string => $payload->externalKey, $parsed->items);
        $completed = $keys === [] ? [] : $this->em->createQuery('SELECT DISTINCT i.externalKey FROM App\\Entity\\ImportItem i JOIN i.batch b WHERE b.user = :user AND i.externalKey IN (:keys) AND i.state IN (:states)')
            ->setParameter('user', $user)->setParameter('keys', $keys)->setParameter('states', [ImportItem::STATE_IMPORTED, ImportItem::STATE_MERGED])->getSingleColumnResult();
        foreach ($parsed->items as $payload) if (!in_array($payload->externalKey, $completed, true)) $this->em->persist(new ImportItem($batch, $payload->externalKey, $payload->title, $payload->toArray()));
        $batch->finishWhenEmpty();
        $this->em->persist($batch);
        $this->em->flush();
        return $batch;
    }
}
