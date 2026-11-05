<?php

namespace App\Controller;

use App\Entity\LibraryEntry;
use App\Entity\User;
use App\Catalog\CatalogService;
use App\Repository\LibraryEntryRepository;
use App\Repository\SeriesRepository;
use App\Watching\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function __invoke(LibraryEntryRepository $entries, ProgressService $progress, CatalogService $catalog, SeriesRepository $series): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->render('home/index.html.twig', ['featuredSeries' => $series->featuredWithPoster()]);
        }

        $available = [];
        foreach ($entries->findForUser($user) as $entry) {
            if (in_array($entry->getStatus(), [LibraryEntry::STATUS_COMPLETED, LibraryEntry::STATUS_DROPPED, LibraryEntry::STATUS_PAUSED], true)) continue;
            if ($next = $progress->nextEpisode($user, $entry->getSeries())) { $catalog->enrichFromSnapshot($entry->getSeries(), $next['season']->getNumber()); $available[] = ['entry' => $entry, 'season' => $next['season'], 'episode' => $next['episode']]; }
        }
        return $this->render('home/index.html.twig', ['availableEpisodes' => $available]);
    }
}
