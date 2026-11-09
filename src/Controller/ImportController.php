<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Import\ImportManager;
use App\Import\YamtrackRemainderExporter;
use App\Message\ProcessImportItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/imports')]
final class ImportController extends AbstractController
{
    #[Route('', name: 'app_import_upload', methods: ['POST'])]
    public function upload(Request $request, ImportManager $imports, MessageBusInterface $bus): Response
    {
        $user = $this->user();
        if (!$this->isCsrfTokenValid('import-upload', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => 'Choisissez un fichier CSV.'], 422);
        }
        try {
            $batch = $imports->create($user, $file);
            foreach ($batch->getItems() as $item) {
                $bus->dispatch(new ProcessImportItem($item->getId()));
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        return $this->json(['id' => $batch->getId(), 'statusUrl' => $this->generateUrl('app_import_status', ['id' => $batch->getId()])], 202);
    }

    #[Route('/{id}/statut', name: 'app_import_status', methods: ['GET'])]
    public function status(ImportBatch $batch): JsonResponse
    {
        $this->owner($batch);
        $remaining = array_filter($batch->getItems()->toArray(), fn ($item): bool => !\in_array($item->getState(), [\App\Entity\ImportItem::STATE_IMPORTED, \App\Entity\ImportItem::STATE_MERGED], true));

        return $this->json(['state' => $batch->getState(), 'total' => $batch->getTotal(), 'processed' => $batch->getProcessed(), 'imported' => $batch->getImported(), 'merged' => $batch->getMerged(), 'unresolved' => $batch->getUnresolved(), 'remainderUrl' => $batch->getFormat() === 'yamtrack' && $remaining !== [] ? $this->generateUrl('app_import_yamtrack_remainder', ['id' => $batch->getId()]) : null, 'items' => array_map(fn ($item) => ['id' => $item->getId(), 'title' => $item->getTitle(), 'state' => $item->getState(), 'reason' => $item->getReason(), 'coverUrl' => $item->getCoverUrl(), 'candidates' => $item->getCandidates(), 'replayUrl' => $this->generateUrl('app_import_replay', ['batch' => $batch->getId(), 'item' => $item->getId()])], $batch->getItems()->toArray())]);
    }

    #[Route('/{id}/reste-yamtrack', name: 'app_import_yamtrack_remainder', methods: ['GET'])]
    public function remainder(ImportBatch $batch, YamtrackRemainderExporter $exporter): Response
    {
        $this->owner($batch);
        if ($batch->getFormat() !== 'yamtrack') {
            throw $this->createNotFoundException();
        }

        return $exporter->stream($batch);
    }

    #[Route('/{batch}/items/{item}/rejouer', name: 'app_import_replay', methods: ['POST'])]
    public function replay(ImportBatch $batch, \App\Entity\ImportItem $item, Request $request, MessageBusInterface $bus, EntityManagerInterface $em): JsonResponse
    {
        $this->owner($batch);
        if ($item->getBatch()->getId() !== $batch->getId() || !$this->isCsrfTokenValid('import-replay', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        } $candidate = $request->request->getString('candidate');
        if (!\in_array($candidate, array_column($item->getCandidates(), 'id'), true)) {
            return $this->json(['error' => 'Choix invalide.'], 422);
        } $batch->reopenUnresolved();
        $item->replay($candidate);
        $em->flush();
        $bus->dispatch(new ProcessImportItem($item->getId()));

        return $this->json(['queued' => true], 202);
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function owner(ImportBatch $batch): void
    {
        if ($batch->getUser()->getId() !== $this->user()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
