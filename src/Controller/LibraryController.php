<?php

namespace App\Controller;

use App\Entity\LibraryEntry;
use App\Entity\Series;
use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\User;
use App\Repository\LibraryEntryRepository;
use App\Repository\CatalogSourceRepository;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Watching\ProgressService;

class LibraryController extends AbstractController
{
    #[Route('/bibliotheque', name: 'app_library', methods: ['GET'])]
    public function index(Request $request, LibraryEntryRepository $entries, CatalogSourceRepository $sources, ProgressService $progress): Response
    {
        $status = $request->query->has('status') ? (string) $request->query->get('status', '') : LibraryEntry::STATUS_WATCHING; $query=trim((string)$request->query->get('q','')); $genre=trim((string)$request->query->get('genre','')); $network=trim((string)$request->query->get('network',''));
        if ($status !== '' && !isset(LibraryEntry::STATUSES[$status])) { $status = ''; }
        $libraryEntries = $entries->findForUser($this->user(), $status ?: null, $query ?: null); $networks=[]; $genresBySeries=[]; $availableGenres=[]; $availableNetworks=[];
        foreach ($libraryEntries as $entry) { $series=$entry->getSeries(); $payload=$sources->findOneBy(['series'=>$series])?->getPayload() ?? []; $seriesId=$series->getId(); $genresBySeries[$seriesId]=$series->getGenres(); foreach($genresBySeries[$seriesId] as $value)$availableGenres[$value]=true; $networks[$seriesId]=$payload['network']['name'] ?? $payload['webChannel']['name'] ?? null; if($networks[$seriesId])$availableNetworks[$networks[$seriesId]]=true; }
        $libraryEntries=array_values(array_filter($libraryEntries, static fn(LibraryEntry $entry):bool => ($genre==='' || in_array($genre,$genresBySeries[$entry->getSeries()->getId()] ?? [],true)) && ($network==='' || $network===($networks[$entry->getSeries()->getId()] ?? null))));
        $progresses=[]; foreach ($libraryEntries as $entry) $progresses[$entry->getId()]=$progress->seriesProgress($this->user(),$entry->getSeries());
        $genres=array_keys($availableGenres); $networkOptions=array_keys($availableNetworks); sort($genres); sort($networkOptions);
        return $this->render('library/index.html.twig', ['entries' => $libraryEntries, 'networks'=>$networks, 'genresBySeries'=>$genresBySeries, 'genres'=>$genres, 'networkOptions'=>$networkOptions, 'selectedGenre'=>$genre, 'selectedNetwork'=>$network, 'progresses'=>$progresses, 'status' => $status, 'query'=>$query, 'statuses' => LibraryEntry::STATUSES]);
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

    #[Route('/bibliotheque/{id}/reprendre', name: 'app_library_resume', methods: ['GET'])]
    public function resume(int $id, LibraryEntryRepository $entries, ProgressService $progress): Response
    {
        $entry = $this->entry($id, $entries);
        $target = $progress->resumeTarget($this->user(), $entry->getSeries());
        if ($target === null) return $this->redirectToRoute('app_series_show', ['id' => $entry->getSeries()->getId()]);

        $url = $this->generateUrl('app_series_show', ['id' => $entry->getSeries()->getId(), 'saison' => $target['season']->getNumber()]);
        if ($target['episode'] !== null) $url .= '#episode-'.$target['episode']->getId();
        return $this->redirect($url);
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

    #[Route('/bibliotheque/{id}/statut', name: 'app_library_status', methods: ['POST'])]
    public function status(int $id, Request $request, LibraryEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->entry($id, $entries);
        if (!$this->isCsrfTokenValid('library_status_'.$id, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        try { $entry->setStatus((string) $request->request->get('status')); } catch (\InvalidArgumentException) { throw $this->createNotFoundException(); }
        $em->flush(); $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirect($this->returnUrl($request));
    }

    #[Route('/bibliotheque/{id}/retirer', name: 'app_library_remove', methods: ['POST'])]
    public function remove(int $id, Request $request, LibraryEntryRepository $entries, EntityManagerInterface $em): Response
    {
        $entry = $this->entry($id, $entries);
        if (!$this->isCsrfTokenValid('library_remove_'.$id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException(); }
        $em->remove($entry); $em->flush(); $this->addFlash('success', 'Série retirée de votre bibliothèque.');
        return $this->redirectToRoute('app_library');
    }

    #[Route('/bibliotheque/{id}/suivi/{type}/{target}', name: 'app_library_progress', methods: ['POST'], requirements: ['type' => 'episode|season|series', 'target' => '\\d+'])]
    public function progress(int $id, string $type, int $target, Request $request, LibraryEntryRepository $entries, EntityManagerInterface $em, ProgressService $progress): Response
    {
        $entry=$this->entry($id,$entries); if (!$this->isCsrfTokenValid('library_progress_'.$id, (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
        $watched=(string)$request->request->get('watched') === '1'; $seasonCompleted=false;
        try { if($type==='episode') {$episode=$em->find(Episode::class,$target)??throw $this->createNotFoundException();$progress->toggleEpisode($this->user(),$entry,$episode,$watched,(string)$request->request->get('catch_up')==='1');$seasonCompleted=$watched&&$progress->seasonProgress($this->user(),$episode->getSeason())['complete'];} elseif($type==='season') {$season=$em->find(Season::class,$target)??throw $this->createNotFoundException();$progress->toggleSeason($this->user(),$entry,$season,$watched);} else {$progress->toggleSeries($this->user(),$entry,$watched);} } catch (\LogicException) { throw $this->createAccessDeniedException(); }
        $progress->synchronizeLibraryStatus($this->user(), $entry);
        $this->addFlash('success',$watched?'Progression mise à jour.':'Épisode(s) remis non vus.');
        if ($seasonCompleted) {
            $lastSeason = true;
            foreach ($entry->getSeries()->getSeasons() as $season) if ($season->getNumber() > $episode->getSeason()->getNumber()) $lastSeason = false;
            if ($lastSeason) return $this->redirectToRoute('app_series_show', ['id' => $entry->getSeries()->getId(), 'season_completed' => 1]);
        }
        $referer = $this->returnUrl($request);
        $anchor = (string) $request->request->get('return_anchor', '');
        if ($seasonCompleted) $referer .= (str_contains($referer, '?') ? '&' : '?').'season_completed=1';
        if ($type === 'episode' && preg_match('/^episode-\\d+$/', $anchor)) $referer .= '#'.$anchor;
        return $this->redirect($referer);
    }

    private function user(): User { $user = $this->getUser(); if (!$user instanceof User) { throw $this->createAccessDeniedException(); } return $user; }
    private function returnUrl(Request $request): string { $url=(string)$request->headers->get('referer', ''); if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url; $parts=parse_url($url); if (is_array($parts) && ($parts['host'] ?? null) === $request->getHost() && in_array($parts['scheme'] ?? null, ['http','https'], true)) return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : ''); return $this->generateUrl('app_library'); }
    private function entry(int $id, LibraryEntryRepository $entries): LibraryEntry { $entry = $entries->find($id) ?? throw $this->createNotFoundException(); if ($entry->getUser()->getId() !== $this->user()->getId()) { throw $this->createAccessDeniedException(); } return $entry; }
    private function date(mixed $value): ?\DateTimeImmutable { if (!is_string($value) || $value === '') { return null; } $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value); if (!$date || $date->format('Y-m-d') !== $value) { throw new \InvalidArgumentException(); } return $date; }
}
