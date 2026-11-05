<?php

namespace App\Tests\Functional;

use App\Entity\LibraryEntry;
use App\Entity\CatalogSource;
use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LibraryTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM episode_progress');
        $connection->executeStatement('DELETE FROM library_entry');
        $connection->executeStatement('DELETE FROM catalog_source');
        $connection->executeStatement('DELETE FROM series');
        $connection->executeStatement('DELETE FROM user');
    }

    public function testAUserCanAddUpdateFilterAndRemoveTheirOwnLibraryEntry(): void
    {
        $user = $this->user('lectrice@example.test');
        $series = (new Series())->setTitle('Severance');
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/series/'.$series->getId());
        $this->client->submit($crawler->selectButton('Ajouter à ma bibliothèque')->form());
        self::assertResponseRedirects('/series/'.$series->getId());

        $entry = $this->em->getRepository(LibraryEntry::class)->findOneBy(['user' => $user, 'series' => $series]);
        self::assertInstanceOf(LibraryEntry::class, $entry);
        self::assertSame(LibraryEntry::STATUS_WATCHLIST, $entry->getStatus());

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer ma fiche')->form([
            'status' => LibraryEntry::STATUS_WATCHING, 'note' => 'À reprendre ce week-end.', 'started_at' => '2026-11-02', 'finished_at' => '',
        ]));
        self::assertResponseRedirects('/series/'.$series->getId());
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_WATCHING, $entry->getStatus());
        self::assertSame('À reprendre ce week-end.', $entry->getNote());
        self::assertSame('2026-11-02', $entry->getStartedAt()?->format('Y-m-d'));

        $crawler = $this->client->request('GET', '/bibliotheque?status=watching');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.library-card h2', 'Severance');

        $crawler = $this->client->request('GET', '/series/'.$series->getId());
        $statusToken = $crawler->filter('form.status-actions input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/bibliotheque/'.$entry->getId().'/statut', ['_token' => $statusToken, 'status' => LibraryEntry::STATUS_PAUSED]);
        self::assertResponseRedirects('/series/'.$series->getId());
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_PAUSED, $entry->getStatus());

        $crawler = $this->client->request('GET', '/series/'.$series->getId());
        $this->client->submit($crawler->selectButton('Retirer de ma bibliothèque')->form());
        self::assertResponseRedirects('/bibliotheque');
        self::assertNull($this->em->getRepository(LibraryEntry::class)->findOneBy(['user' => $user, 'series' => $series]));
    }

    public function testLibraryEntriesArePrivateToTheirOwner(): void
    {
        $owner = $this->user('owner@example.test'); $other = $this->user('other@example.test'); $series = (new Series())->setTitle('Private Show');
        $this->em->persist($owner); $this->em->persist($other); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($owner, $series); $this->em->persist($entry); $this->em->flush();

        $this->client->loginUser($other);
        $this->client->request('GET', '/bibliotheque');
        self::assertResponseIsSuccessful(); self::assertSelectorNotExists('.library-card');
        $this->client->request('POST', '/bibliotheque/'.$entry->getId().'/retirer', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em->getRepository(LibraryEntry::class)->find($entry->getId()));
    }

    public function testStatusUpdateNeverRedirectsToAnExternalReferer(): void
    {
        $user = $this->user('return@example.test');
        $series = (new Series())->setTitle('Safe return');
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($user, $series); $this->em->persist($entry); $this->em->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/series/'.$series->getId());
        $token = $crawler->filter('form.status-actions input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/bibliotheque/'.$entry->getId().'/statut', ['_token' => $token, 'status' => LibraryEntry::STATUS_PAUSED], [], ['HTTP_REFERER' => 'https://attacker.example/landing']);

        self::assertResponseRedirects('/bibliotheque');
    }

    public function testLibraryCanDisplayAndFilterProviderGenreAndNetworkTags(): void
    {
        $user = $this->user('tag-filter@example.test');
        $drama = (new Series())->setTitle('Arte drama')->setGenres(['Drame', 'Thriller']);
        $comedy = (new Series())->setTitle('Comedy network')->setGenres(['Comédie']);
        $this->em->persist($user); $this->em->persist($drama); $this->em->persist($comedy); $this->em->flush();
        $arte = new CatalogSource('tvmaze', '101', $drama); $arte->refresh(['network' => ['name' => 'Arte']], new \DateTimeImmutable('+1 day'));
        $netflix = new CatalogSource('tvmaze', '102', $comedy); $netflix->refresh(['webChannel' => ['name' => 'Netflix']], new \DateTimeImmutable('+1 day'));
        $this->em->persist($arte); $this->em->persist($netflix); $this->em->persist(new LibraryEntry($user, $drama)); $this->em->persist(new LibraryEntry($user, $comedy)); $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/bibliotheque?status=&genre=Drame&network=Arte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.library-card', 'Arte drama');
        self::assertSelectorTextContains('.library-card', 'Drame');
        self::assertSelectorTextContains('.library-card', 'Arte');
        self::assertSelectorNotExists('.library-card :contains("Comedy network")');
        self::assertSelectorExists('select[name="genre"] option[value="Drame"]');
        self::assertSelectorExists('select[name="network"] option[value="Arte"]');
    }

    public function testEveryLibraryStatusResumesAtTheFirstUnwatchedAiredEpisode(): void
    {
        $user = $this->user('resume-statuses@example.test');
        $series = (new Series())->setTitle('Resume show');
        $specials = new Season(0);
        $special = new Episode(1); $special->setTitle('Special'); $special->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $specials->addEpisode($special);
        $firstSeason = new Season(1);
        $first = new Episode(1); $first->setTitle('First'); $first->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $firstSeason->addEpisode($first);
        $currentSeason = new Season(2);
        $watched = new Episode(1); $watched->setTitle('Already watched'); $watched->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $next = new Episode(2); $next->setTitle('Next to watch'); $next->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $currentSeason->addEpisode($watched); $currentSeason->addEpisode($next);
        $series->addSeason($specials); $series->addSeason($firstSeason); $series->addSeason($currentSeason);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($user, $series);
        $this->em->persist($entry);
        $this->em->persist(new \App\Entity\EpisodeProgress($user, $first));
        $this->em->persist(new \App\Entity\EpisodeProgress($user, $watched));
        $this->em->flush();

        $this->client->loginUser($user);
        foreach (array_keys(LibraryEntry::STATUSES) as $status) {
            $entry->setStatus($status); $this->em->flush();
            $this->client->request('GET', '/bibliotheque/'.$entry->getId().'/reprendre');
            self::assertResponseRedirects('/series/'.$series->getId().'?saison=2#episode-'.$next->getId());
        }
    }

    public function testACompletedSeriesResumesAtItsLastStandardSeason(): void
    {
        $user = $this->user('resume-completed@example.test');
        $series = (new Series())->setTitle('Completed resume show');
        $firstSeason = new Season(1);
        $first = new Episode(1); $first->setTitle('First'); $first->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $firstSeason->addEpisode($first);
        $lastSeason = new Season(2);
        $last = new Episode(1); $last->setTitle('Last'); $last->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $lastSeason->addEpisode($last);
        $series->addSeason($firstSeason); $series->addSeason($lastSeason);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($user, $series); $entry->setStatus(LibraryEntry::STATUS_COMPLETED);
        $this->em->persist($entry);
        $this->em->persist(new \App\Entity\EpisodeProgress($user, $first));
        $this->em->persist(new \App\Entity\EpisodeProgress($user, $last));
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/bibliotheque/'.$entry->getId().'/reprendre');

        self::assertResponseRedirects('/series/'.$series->getId().'?saison=2');
    }

    public function testSeriesPageShowsSeasonAndEpisodeArtworkWithTheLibraryModal(): void
    {
        $user = $this->user('viewer@example.test');
        $series = (new Series())->setTitle('With artwork')->setCast([['name' => 'Anna Actrice', 'character' => 'Héroïne', 'imageUrl' => '/media/posters/actor.jpg']]);
        $season = new Season(1); $season->setName('Première saison'); $season->setCoverUrl('/media/posters/season.jpg');
        $episode = new Episode(1); $episode->setTitle('Pilot'); $episode->setCoverUrl('/media/posters/episode.jpg'); $episode->setRuntime(52);
        $season->addEpisode($episode); $series->addSeason($season);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $this->em->persist(new LibraryEntry($user, $series)); $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/series/'.$series->getId().'?saison=1');
        self::assertSelectorExists('dialog.library-modal[data-modal-target="dialog"]');
        self::assertSelectorExists('.series-poster[src="/media/posters/season.jpg"]');
        self::assertSelectorTextContains('.episode-card', '52 min');
        self::assertSelectorExists('.episode-card img[src="/media/posters/episode.jpg"]');
        $this->client->request('GET', '/series/'.$series->getId());
        self::assertSelectorTextContains('.cast-card', 'Anna Actrice');
        self::assertSelectorTextContains('.cast-card', 'Héroïne');
        self::assertSelectorExists('.cast-card a[href="/catalogue?acteur=Anna%20Actrice"]');
        $other = (new Series())->setTitle('Second rôle')->setCast([['name' => 'Anna Actrice', 'character' => null, 'imageUrl' => null]]);
        $this->em->persist($other); $this->em->flush();
        $this->client->request('GET', '/catalogue?acteur=Anna%20Actrice');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-header h1', 'Séries avec Anna Actrice');
        self::assertSelectorTextContains('.result-grid', 'With artwork');
        self::assertSelectorTextContains('.result-grid', 'Second rôle');
    }

    public function testWatchingAnAdvancedEpisodeCanCatchUpAndBeToggledBack(): void
    {
        $user=$this->user('progress@example.test'); $series=(new Series())->setTitle('Progress show'); $season=new Season(1);
        foreach ([1,2,3] as $number) { $episode=new Episode($number); $episode->setTitle('Episode '.$number); $episode->setAirDate(new \DateTimeImmutable('2026-01-01')); if ($number === 1) $episode->setCoverUrl('/media/posters/episode-1.jpg'); $season->addEpisode($episode); }
        $series->addSeason($season); $this->em->persist($user); $this->em->persist($series); $this->em->flush(); $entry=new LibraryEntry($user,$series); $this->em->persist($entry); $this->em->flush();
        $this->client->loginUser($user); $this->client->request('GET','/'); self::assertSelectorTextContains('.available-episodes', 'S01E01'); self::assertSelectorExists('.available-episodes img[src="/media/posters/episode-1.jpg"]'); $crawler=$this->client->request('GET','/series/'.$series->getId().'?saison=1'); $token=$crawler->filter('form.episode-toggle input[name="_token"]')->last()->attr('value'); $first=$season->getEpisodes()->first(); $third=$season->getEpisodes()->last();
        self::assertSelectorExists('#episode-'.$first->getId().'[data-next-unwatched]');
        self::assertSelectorNotExists('#episode-'.$third->getId().'[data-next-unwatched]');
        $returnUrl='/series/'.$series->getId().'?saison=1';
        $this->client->request('POST','/bibliotheque/'.$entry->getId().'/suivi/episode/'.$third->getId(),['_token'=>$token,'watched'=>'1','catch_up'=>'1','return_anchor'=>'episode-'.$third->getId()],[],['HTTP_REFERER'=>$returnUrl]); self::assertResponseRedirects('/series/'.$series->getId().'?season_completed=1');
        self::assertSame(3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM episode_progress'));
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_COMPLETED, $entry->getStatus());
        $crawler=$this->client->request('GET','/series/'.$series->getId().'?saison=1');
        self::assertCount(0, $crawler->filter('#episode-'.$third->getId().' form[data-watch-toggle-catch-up]'));
        self::assertSelectorTextContains('h1 .season-completion', 'Saison entièrement vue');
        self::assertSelectorExists('#episode-'.$third->getId().'.is-watched button[aria-pressed="true"] svg path[d="M5 12l4 4L19 6"]');
        self::assertSelectorExists('[data-controller~="episode-feedback"] #episode-'.$third->getId());
        $this->client->request('GET', '/series/'.$series->getId().'?season_completed=1');
        self::assertSelectorTextContains('h1 .series-completion', 'Série entièrement vue');
        $this->client->request('POST','/bibliotheque/'.$entry->getId().'/suivi/episode/'.$third->getId(),['_token'=>$token,'watched'=>'0','return_anchor'=>'episode-'.$third->getId()],[],['HTTP_REFERER'=>$returnUrl]); self::assertResponseRedirects($returnUrl.'#episode-'.$third->getId());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM episode_progress'));
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_WATCHING, $entry->getStatus());
    }

    public function testManualWatchingStatusIsNotOverwrittenWhenViewingACompletedSeries(): void
    {
        $user = $this->user('manual-status@example.test');
        $series = (new Series())->setTitle('Manual status show');
        $season = new Season(1);
        $episode = new Episode(1); $episode->setTitle('Final episode'); $episode->setAirDate(new \DateTimeImmutable('2026-01-01'));
        $season->addEpisode($episode); $series->addSeason($season);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($user, $series); $entry->setStatus(LibraryEntry::STATUS_COMPLETED);
        $this->em->persist($entry); $this->em->persist(new \App\Entity\EpisodeProgress($user, $episode)); $this->em->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/series/'.$series->getId());
        $token = $crawler->filter('form.status-actions input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/bibliotheque/'.$entry->getId().'/statut', ['_token' => $token, 'status' => LibraryEntry::STATUS_WATCHING]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/series/'.$series->getId());
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_WATCHING, $entry->getStatus());
    }

    public function testUpcomingEpisodeShowsItsCountdownInsteadOfArtwork(): void
    {
        $user = $this->user('future@example.test');
        $series = (new Series())->setTitle('Future show');
        $season = new Season(24);
        $episode = new Episode(1);
        $episode->setTitle('Future episode');
        $episode->setAiredAt(new \DateTimeImmutable('2099-01-15 20:00:00+00:00'));
        $episode->setCoverUrl('/media/posters/future-episode.jpg');
        $season->addEpisode($episode); $series->addSeason($season);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $this->em->persist(new LibraryEntry($user, $series)); $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/series/'.$series->getId().'?saison=24');

        self::assertSelectorTextContains('.episode-card', 'Future episode');
        self::assertSelectorExists('.episode-countdown');
        self::assertSelectorTextContains('.episode-countdown', 'avant diffusion');
        self::assertSelectorNotExists('.episode-card img[src="/media/posters/future-episode.jpg"]');
    }

    public function testCalendarShowsTheSeasonCoverBetweenTheAiringDetailsAndEpisodeInformation(): void
    {
        $user = $this->user('calendar@example.test');
        $series = (new Series())->setTitle('Calendar show');
        $season = new Season(2); $season->setCoverUrl('/media/posters/calendar-season.jpg');
        $episode = new Episode(3); $episode->setTitle('Calendar episode'); $episode->setAirDate(new \DateTimeImmutable('2099-01-15')); $episode->setAiredAt(new \DateTimeImmutable('2099-01-15 20:00:00+00:00'));
        $season->addEpisode($episode); $series->addSeason($season);
        $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $this->em->persist(new LibraryEntry($user, $series)); $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/calendrier');

        self::assertSelectorExists('.calendar-card');
        self::assertSelectorExists('.calendar-card__cover img[src="/media/posters/calendar-season.jpg"]');
        self::assertSelectorTextContains('.calendar-card__body', 'Calendar episode');
    }

    public function testWatchingTheFirstEpisodeMovesAWatchlistEntryToWatching(): void
    {
        $user = $this->user('first-episode@example.test');
        $series = (new Series())->setTitle('First episode show');
        $season = new Season(1);
        foreach ([1, 2] as $number) {
            $episode = new Episode($number); $episode->setTitle('Episode '.$number); $episode->setAirDate(new \DateTimeImmutable('2026-01-01')); $season->addEpisode($episode);
        }
        $series->addSeason($season); $this->em->persist($user); $this->em->persist($series); $this->em->flush();
        $entry = new LibraryEntry($user, $series); $this->em->persist($entry); $this->em->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/series/'.$series->getId().'?saison=1');
        $token = $crawler->filter('form.episode-toggle input[name="_token"]')->first()->attr('value');
        $first = $season->getEpisodes()->first();
        $this->client->request('POST', '/bibliotheque/'.$entry->getId().'/suivi/episode/'.$first->getId(), ['_token' => $token, 'watched' => '1']);
        self::assertResponseRedirects();
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_WATCHING, $entry->getStatus());
    }

    private function user(string $email): User
    {
        $user = (new User())->setEmail($email);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        return $user;
    }
}
