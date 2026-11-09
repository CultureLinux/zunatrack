<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Episode;

final class EpisodePushPayload
{
    /** @return array{title:string,body:string,url:string,tag:string,icon:?string,image:?string} */
    public static function forEpisode(Episode $episode): array
    {
        $season = $episode->getSeason();
        $series = $season->getSeries();
        $poster = $series->getPosterUrl();

        return [
            'title' => $series->getTitle(),
            'body' => \sprintf('%s — S%02dE%02d %s', $series->getTitle(), $season->getNumber(), $episode->getNumber(), $episode->getTitle()),
            'url' => $series->getId() === null ? '/' : \sprintf('/series/%d?saison=%d#episode-%d', $series->getId(), $season->getNumber(), $episode->getId()),
            'tag' => \sprintf('episode-%d', $episode->getId()),
            'icon' => $poster,
            'image' => $episode->getCoverUrl() ?? $poster,
        ];
    }
}
