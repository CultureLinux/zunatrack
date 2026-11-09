<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Episode;
use App\Entity\EpisodeProgress;
use App\Entity\LibraryEntry;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StatisticsTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private AbstractBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM episode_progress');
        $connection->executeStatement('DELETE FROM library_entry');
        $connection->executeStatement('DELETE FROM series');
        $connection->executeStatement('DELETE FROM user');
    }

    public function testStatisticsArePrivateAndSummarizeAUsersViewing(): void
    {
        $owner = $this->user('stats-owner@example.test');
        $other = $this->user('stats-other@example.test');
        $series = new Series()->setTitle('Statistical Show')->setGenres(['Drame', 'Mystère']);
        $season = new Season(1);
        $watched = new Episode(1);
        $watched->setTitle('Vu');
        $watched->setAirDate(new \DateTimeImmutable('-2 days'));
        $watched->setRuntime(1562);
        $unwatched = new Episode(2);
        $unwatched->setTitle('À voir');
        $unwatched->setAirDate(new \DateTimeImmutable('-1 day'));
        $unwatched->setRuntime(48);
        $season->addEpisode($watched);
        $season->addEpisode($unwatched);
        $series->addSeason($season);
        $otherSeries = new Series()->setTitle('Private other show');
        $this->em->persist($owner);
        $this->em->persist($other);
        $this->em->persist($series);
        $this->em->persist($otherSeries);
        $this->em->flush();
        $entry = new LibraryEntry($owner, $series);
        $this->em->persist($entry);
        $this->em->persist(new EpisodeProgress($owner, $watched));
        $this->em->persist(new LibraryEntry($other, $otherSeries));
        $this->em->flush();

        $this->client->loginUser($owner);
        $this->client->request('GET', '/statistiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller~="statistics-motion"]');
        self::assertSelectorTextContains('h1', 'Vos statistiques');
        self::assertSelectorTextContains('[data-stat="watched-episodes"]', '1');
        self::assertSelectorTextContains('[data-stat="watch-time"]', '1 j 2 h 2 min');
        self::assertSelectorTextContains('[data-stat="catch-up"]', '1');
        self::assertSelectorTextContains('.statistics-top-series', 'Statistical Show');
        self::assertSelectorTextContains('.statistics-tags', 'Drame');
        self::assertSelectorNotExists('.statistics-top-series :contains("Private other show")');
        self::assertSelectorExists('a.statistics-nav[href="/statistiques"]');
        self::assertSelectorExists('.mobile-menu a[href="/statistiques"]');
    }

    public function testAnonymousVisitorsAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/statistiques');

        self::assertResponseRedirects('/connexion');
    }

    public function testPausedAndDroppedSeriesDoNotInflateTheCatchUpCount(): void
    {
        $user = $this->user('paused-stats@example.test');
        $watching = $this->seriesWithAiredEpisode('Watching show');
        $paused = $this->seriesWithAiredEpisode('Paused show');
        $dropped = $this->seriesWithAiredEpisode('Dropped show');
        $this->em->persist($user);
        $this->em->persist($watching);
        $this->em->persist($paused);
        $this->em->persist($dropped);
        $this->em->flush();

        $this->em->persist(new LibraryEntry($user, $watching));
        $pausedEntry = new LibraryEntry($user, $paused);
        $pausedEntry->setStatus(LibraryEntry::STATUS_PAUSED);
        $droppedEntry = new LibraryEntry($user, $dropped);
        $droppedEntry->setStatus(LibraryEntry::STATUS_DROPPED);
        $this->em->persist($pausedEntry);
        $this->em->persist($droppedEntry);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/statistiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-stat="catch-up"]', '1');
    }

    public function testCompletedSeriesAreExcludedFromTheMostAdvancedList(): void
    {
        $user = $this->user('completed-stats@example.test');
        $active = $this->seriesWithAiredEpisode('Active show');
        $completed = $this->seriesWithAiredEpisode('Completed show');
        $this->em->persist($user);
        $this->em->persist($active);
        $this->em->persist($completed);
        $this->em->flush();

        $activeEntry = new LibraryEntry($user, $active);
        $completedEntry = new LibraryEntry($user, $completed);
        $completedEntry->setStatus(LibraryEntry::STATUS_COMPLETED);
        $this->em->persist($activeEntry);
        $this->em->persist($completedEntry);
        $this->em->persist(new EpisodeProgress($user, $completed->getSeasons()->first()->getEpisodes()->first()));
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/statistiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.statistics-top-series', 'Active show');
        self::assertSelectorNotExists('.statistics-top-series :contains("Completed show")');
    }

    public function testStatisticsShowAnEmptyStateWithoutLibraryData(): void
    {
        $user = $this->user('empty-stats@example.test');
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/statistiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.statistics-empty', 'Ajoutez une série');
    }

    private function user(string $email): User
    {
        $user = new User()->setEmail($email);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));

        return $user;
    }

    private function seriesWithAiredEpisode(string $title): Series
    {
        $series = new Series()->setTitle($title);
        $season = new Season(1);
        $episode = new Episode(1);
        $episode->setTitle('Épisode diffusé');
        $episode->setAirDate(new \DateTimeImmutable('-1 day'));
        $season->addEpisode($episode);
        $series->addSeason($season);

        return $series;
    }
}
