<?php

namespace App\Controller;

use App\Catalog\CatalogService;
use App\Entity\User;
use App\Repository\CatalogSourceRepository;
use App\Repository\LibraryEntryRepository;
use App\Repository\SeriesRepository;
use App\Watching\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogController extends AbstractController
{
    #[Route('/catalogue', name: 'app_catalog')]
    public function index(Request $request, CatalogService $catalog, SeriesRepository $series): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $actor = trim((string) $request->query->get('acteur', ''));
        $actorResults = $actor !== '' ? $series->findByActor($actor) : [];
        $results = $actor !== '' ? $actorResults : ($query !== '' ? $catalog->search($query) : []);
        $suggestions = $actor === '' && $query === '' ? $series->suggestForUser($this->getUser() instanceof User ? $this->getUser() : null) : [];
        return $this->render('catalog/index.html.twig', compact('query', 'actor', 'results', 'suggestions'));
    }
    #[Route('/catalogue/tvmaze/{id}', name: 'app_catalog_import', requirements: ['id'=>'\d+'])]
    public function import(string $id, CatalogService $catalog): Response { $series=$catalog->import($id); return $this->redirectToRoute('app_series_show',['id'=>$series->getId()]); }

    #[Route('/series/{id}', name: 'app_series_show')]
    public function show(int $id, Request $request, SeriesRepository $series, LibraryEntryRepository $entries, CatalogSourceRepository $sources, CatalogService $catalog, ProgressService $progress): Response
    {
        $show=$series->find($id)??throw $this->createNotFoundException(); $selectedSeason=$request->query->getInt('saison')?:null; $catalog->enrichFromSnapshot($show,$selectedSeason); $selected=null; $next=null;
        foreach($show->getSeasons() as $season){if($season->getNumber()===$selectedSeason)$selected=$season;if($selectedSeason!==null&&$season->getNumber()>$selectedSeason&&$next===null)$next=$season;}
        $payload=[]; $source=$sources->findOneBy(['series'=>$show]); if($selectedSeason!==null) foreach(($source?->getPayload()['_embedded']['seasons']??[]) as $row) if((int)($row['number']??-1)===$selectedSeason){$payload=$row;break;}
        $user=$this->getUser(); $entry=$user instanceof User?$entries->findForUserAndSeries($user,$show):null;
        return $this->render('catalog/show.html.twig',['series'=>$show,'entry'=>$entry,'selectedSeason'=>$selectedSeason,'selectedSeasonEntity'=>$selected,'nextSeason'=>$next,'seasonPayload'=>$payload,'seriesProgress'=>$entry?$progress->seriesProgress($user,$show):null,'seasonProgress'=>$entry?$progress->allSeasonProgress($user,$show):[],'watchedEpisodes'=>$entry&&$selected?$progress->watchedMap($user,$selected):[],'catchUpEpisodes'=>$entry&&$selected?$progress->catchUpMap($user,$selected):[],'nextEpisode'=>$entry?$progress->nextEpisode($user,$show):null]);
    }
}
