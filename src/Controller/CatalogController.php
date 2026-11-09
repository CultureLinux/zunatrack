<?php

declare(strict_types=1);

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
    public function index(Request $request, CatalogService $catalog, SeriesRepository $series, LibraryEntryRepository $entries, CatalogSourceRepository $sources, ProgressService $progress): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $actor = trim((string) $request->query->get('acteur', ''));
        $actorResults = $actor !== '' ? $series->findByActor($actor) : [];
        $results = $actor !== '' ? $actorResults : ($query !== '' ? $catalog->search($query) : []);
        $suggestions = $actor === '' && $query === '' ? $series->suggestForUser($this->getUser() instanceof User ? $this->getUser() : null) : [];
        $suggestionGroups = $this->suggestionGroups($suggestions);
        $seenResultIds = [];
        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($entries->findForUser($user) as $entry) {
                if ($progress->seriesProgress($user, $entry->getSeries())['complete']) {
                    $seenResultIds[(string) $entry->getSeries()->getId()] = true;
                    if ($source = $sources->findOneBy(['series' => $entry->getSeries()])) {
                        $seenResultIds[$source->getExternalId()] = true;
                    }
                }
            }
        }

        return $this->render('catalog/index.html.twig', compact('query', 'actor', 'results', 'suggestionGroups', 'seenResultIds'));
    }

    #[Route('/catalogue/tvmaze/{id}', name: 'app_catalog_import', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function import(string $id, Request $request, CatalogService $catalog): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('catalog_import_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        } $series = $catalog->import($id);

        return $this->redirectToRoute('app_series_show', ['id' => $series->getId()]);
    }

    #[Route('/series/{id}', name: 'app_series_show')]
    public function show(int $id, Request $request, SeriesRepository $series, LibraryEntryRepository $entries, CatalogSourceRepository $sources, CatalogService $catalog, ProgressService $progress): Response
    {
        $show = $series->find($id) ?? throw $this->createNotFoundException();
        $selectedSeason = $request->query->getInt('saison') ?: null;
        $user = $this->getUser();
        if ($user instanceof User) {
            $catalog->enrichFromSnapshot($show, $selectedSeason);
        }
        $selected = null;
        $next = null;
        foreach ($show->getSeasons() as $season) {
            if ($season->getNumber() === $selectedSeason) {
                $selected = $season;
            }if ($selectedSeason !== null && $season->getNumber() > $selectedSeason && $next === null) {
                $next = $season;
            }
        }
        $payload = [];
        $source = $sources->findOneBy(['series' => $show]);
        if ($selectedSeason !== null) {
            foreach (($source?->getPayload()['_embedded']['seasons'] ?? []) as $row) {
                if ((int) ($row['number'] ?? -1) === $selectedSeason) {
                    $payload = $row;
                    break;
                }
            }
        }
        $entry = $user instanceof User ? $entries->findForUserAndSeries($user, $show) : null;

        return $this->render('catalog/show.html.twig', ['series' => $show, 'entry' => $entry, 'selectedSeason' => $selectedSeason, 'selectedSeasonEntity' => $selected, 'nextSeason' => $next, 'seasonPayload' => $payload, 'seriesProgress' => $entry ? $progress->seriesProgress($user, $show) : null, 'seasonProgress' => $entry ? $progress->allSeasonProgress($user, $show) : [], 'watchedEpisodes' => $entry && $selected ? $progress->watchedMap($user, $selected) : [], 'catchUpEpisodes' => $entry && $selected ? $progress->catchUpMap($user, $selected) : [], 'nextEpisode' => $entry ? $progress->nextEpisode($user, $show) : null]);
    }

    /** @param list<\App\Entity\Series> $suggestions @return list<array{title:string,series:list<\App\Entity\Series>}> */
    private function suggestionGroups(array $suggestions): array
    {
        $count = \count($suggestions);
        if ($count < 24) {
            return $suggestions === [] ? [] : [['title' => 'À découvrir', 'series' => $suggestions]];
        }
        $genres = [];
        foreach ($suggestions as $suggestion) {
            foreach ($suggestion->getGenres() as $genre) {
                $genre = trim((string) $genre);
                if ($genre === '') {
                    continue;
                }
                $key = mb_strtolower($genre);
                $genres[$key]['title'] ??= $genre;
                $genres[$key]['count'] = ($genres[$key]['count'] ?? 0) + 1;
            }
        }
        uasort($genres, fn (array $left, array $right): int => $right['count'] <=> $left['count'] ?: strcasecmp($left['title'], $right['title']));
        $selected = \array_slice(array_keys($genres), 0, $count >= 60 ? 6 : 3);
        $groups = array_fill_keys($selected, []);
        $others = [];
        foreach ($suggestions as $suggestion) {
            $keys = array_map(fn (string $genre): string => mb_strtolower(trim($genre)), $suggestion->getGenres());
            $group = array_find($selected, fn (string $genre): bool => \in_array($genre, $keys, true));
            if ($group === null) {
                $others[] = $suggestion;
            } else {
                $groups[$group][] = $suggestion;
            }
        }
        $result = [];
        foreach ($selected as $genre) {
            if (\count($groups[$genre]) < 3) {
                $others = [...$others, ...$groups[$genre]];
                continue;
            }
            $result[] = ['title' => $genres[$genre]['title'], 'series' => $groups[$genre]];
        }
        if ($others !== []) {
            $result[] = ['title' => 'Autres à découvrir', 'series' => $others];
        }

        return $result;
    }
}
