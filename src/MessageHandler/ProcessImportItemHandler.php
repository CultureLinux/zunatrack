<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ImportItem;
use App\Import\ImportProcessor;
use App\Message\ProcessImportItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessImportItemHandler
{
    public function __construct(private EntityManagerInterface $em, private ImportProcessor $processor)
    {
    }

    public function __invoke(ProcessImportItem $message): void
    {
        $item = $this->em->find(ImportItem::class, $message->itemId);
        if ($item instanceof ImportItem && $item->getState() === ImportItem::STATE_PENDING) {
            $this->processor->process($item);
        }
    }
}
