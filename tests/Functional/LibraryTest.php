<?php

namespace App\Tests\Functional;

use App\Entity\LibraryEntry;
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
        $this->client->loginUser($user); $this->client->request('GET','/'); self::assertSelectorTextContains('.available-episodes', 'S01E01'); self::assertSelectorExists('.available-episodes img[src="/media/posters/episode-1.jpg"]'); $crawler=$this->client->request('GET','/series/'.$series->getId().'?saison=1'); $token=$crawler->filter('form.episode-toggle input[name="_token"]')->last()->attr('value'); $third=$season->getEpisodes()->last();
        $this->client->request('POST','/bibliotheque/'.$entry->getId().'/suivi/episode/'.$third->getId(),['_token'=>$token,'watched'=>'1','catch_up'=>'1']); self::assertResponseRedirects();
        self::assertSame(3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM episode_progress'));
        $this->em->clear();
        $entry = $this->em->getRepository(LibraryEntry::class)->find($entry->getId());
        self::assertSame(LibraryEntry::STATUS_COMPLETED, $entry->getStatus());
        $crawler=$this->client->request('GET','/series/'.$series->getId().'?saison=1');
        self::assertCount(0, $crawler->filter('#episode-'.$third->getId().' form[data-watch-toggle-catch-up]'));
        $returnUrl='/series/'.$series->getId().'?saison=1';
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
