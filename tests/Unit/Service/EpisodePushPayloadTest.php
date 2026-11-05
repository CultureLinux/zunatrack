<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Series;
use App\Service\EpisodePushPayload;
use PHPUnit\Framework\TestCase;

final class EpisodePushPayloadTest extends TestCase
{
    public function testUsesTheEpisodeImageAndSeriesPosterAsNotificationArtwork(): void
    {
        $series = (new Series())->setTitle('Example show')->setPosterUrl('/media/posters/show.jpg');
        $season = new Season(2); $episode = new Episode(3); $episode->setTitle('The return'); $episode->setCoverUrl('/media/posters/episode.jpg');
        $season->addEpisode($episode); $series->addSeason($season);

        $payload = EpisodePushPayload::forEpisode($episode);

        self::assertSame('Example show', $payload['title']);
        self::assertSame('Example show — S02E03 The return', $payload['body']);
        self::assertSame('/media/posters/episode.jpg', $payload['image']);
        self::assertSame('/media/posters/show.jpg', $payload['icon']);
    }

    public function testUsesTheSeriesPosterWhenTheEpisodeHasNoImage(): void
    {
        $series = (new Series())->setTitle('Example show')->setPosterUrl('/media/posters/show.jpg');
        $season = new Season(1); $episode = new Episode(1); $episode->setTitle('Pilot');
        $season->addEpisode($episode); $series->addSeason($season);

        self::assertSame('/media/posters/show.jpg', EpisodePushPayload::forEpisode($episode)['image']);
    }
}
