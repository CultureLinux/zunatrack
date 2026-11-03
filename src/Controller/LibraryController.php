<?php

namespace App\Controller;

use App\Entity\LibraryEntry;
use App\Entity\Series;
use App\Entity\User;
use App\Repository\LibraryEntryRepository;
use App\Repository\CatalogSourceRepository;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LibraryController extends AbstractController
{
    #[Route('/bibliotheque', name: 'app_library', methods: ['GET'])]
    public function index(Request $request, LibraryEntryRepository $entries, CatalogSourceRepository $sources): Response
    {
        $status = (string) $request->query->get('status', ''); $query=trim((string)$request->query->get('q',''));
        if ($status !== '' && !isset(LibraryEntry::STATUSES[$status])) { $status = ''; }
        $libraryEntries = $entries->findForUser($this->user(), $status ?: null, $query ?: null); $networks=[];
        foreach ($libraryEntries as $entry) { $payload=$sources->findOneBy(['series'=>$entry->getSeries()])?->getPayload() ?? []; $networks[$entry->getSeries()->getId()]=$payload['network']['name'] ?? $payload['webChannel']['name'] ?? null; }
        return $this->render('library/index.html.twig', ['entries' => $libraryEntries, 'networks'=>$networks, 'status' => $status, 'query'=>$query, 'statuses' => LibraryEntry::STATUSES]);
    }

    #[Route('/bibliotheque/ajouter/{id}', name: 'app_library_add', methods: ['POST'])]
    public function add(int $id, Request $request, SeriesRepository $series, LibraryEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('library_add_'.$id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $show = $series->find($id) ?? throw $this->createNotFoundException();
        if (!$entries->findForUserAndSeries($this->user(), $show)) { $em->persist(new LibraryEntry($this->user(), $show)); $em->flush(); $this->addFlash('success', 'Série ajoutée à votre bibliothèque.'); }
        return $this->redirectToRoute('app_series_show', ['id' => $id]);
    }

    #[Route('/bibliotheque/{id}/modifier', name: 'app_library_update', methods: ['POST'])]
    public function update(int $id, Request $request, LibraryEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->entry($id, $entries);
        if (!$this->isCsrfTokenValid('library_update_'.$id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        try { $entry->setStatus((string) $request->request->get('status')); $entry->setNote($request->request->get('note')); $entry->setStartedAt($this->date($request->request->get('started_at'))); $entry->setFinishedAt($this->date($request->request->get('finished_at'))); } catch (\InvalidArgumentException) { $this->addFlash('error', 'Vérifiez le statut et les dates renseignés.'); return $this->redirectToRoute('app_series_show', ['id' => $entry->getSeries()->getId()]); }
        $em->flush(); $this->addFlash('success', 'Votre fiche a été enregistrée.');
        return $this->redirectToRoute('app_series_show', ['id' => $entry->getSeries()->getId()]);
    }

    #[Route('/bibliotheque/{id}/retirer', name: 'app_library_remove', methods: ['POST'])]
    public function remove(int $id, Request $request, LibraryEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->entry($id, $entries);
        if (!$this->isCsrfTokenValid('library_remove_'.$id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $em->remove($entry); $em->flush(); $this->addFlash('success', 'Série retirée de votre bibliothèque.');
        return $this->redirectToRoute('app_library');
    }

    private function user(): User { $user = $this->getUser(); if (!$user instanceof User) { throw $this->createAccessDeniedException(); } return $user; }
    private function entry(int $id, LibraryEntryRepository $entries): LibraryEntry { $entry = $entries->find($id) ?? throw $this->createNotFoundException(); if ($entry->getUser()->getId() !== $this->user()->getId()) { throw $this->createAccessDeniedException(); } return $entry; }
    private function date(mixed $value): ?\DateTimeImmutable { if (!is_string($value) || $value === '') { return null; } $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value); if (!$date || $date->format('Y-m-d') !== $value) { throw new \InvalidArgumentException(); } return $date; }
}
