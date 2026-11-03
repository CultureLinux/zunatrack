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
        $this->em->refresh($entry);
        self::assertSame(LibraryEntry::STATUS_WATCHING, $entry->getStatus());
        self::assertSame('À reprendre ce week-end.', $entry->getNote());
        self::assertSame('2026-11-02', $entry->getStartedAt()?->format('Y-m-d'));

        $crawler = $this->client->request('GET', '/bibliotheque?status=watching');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.library-card h2', 'Severance');

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
        $series = (new Series())->setTitle('With artwork');
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
    }

    private function user(string $email): User
    {
        $user = (new User())->setEmail($email);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        return $user;
    }
}
