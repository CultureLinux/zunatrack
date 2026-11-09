<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\LibraryEntry;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM library_entry');
        $connection->executeStatement('DELETE FROM series');
        $connection->executeStatement('DELETE FROM user');
    }

    public function testAdminCanExploreDataAndEditAnotherAccount(): void
    {
        $admin = $this->user('admin@example.test', true);
        $member = $this->user('member@example.test');
        $series = new Series()->setTitle('Silo');
        $this->em->persist($admin);
        $this->em->persist($member);
        $this->em->persist($series);
        $this->em->flush();
        $this->em->persist(new LibraryEntry($member, $series));
        $this->em->flush();

        $client = static::getClient();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/statistiques');
        self::assertSelectorExists('.stat-grid a[href="/admin/utilisateurs"]');
        self::assertSelectorExists('.admin-tabs a[href="/admin/series"]');
        self::assertSelectorExists('.admin-tabs a[href="/admin/api-tvmaze"]');
        $client->request('GET', '/admin/series');
        self::assertSelectorTextContains('.admin-table', 'Silo');
        $client->request('GET', '/admin/bibliotheque');
        self::assertSelectorTextContains('.admin-table', 'member@example.test');

        $crawler = $client->request('GET', '/admin/utilisateurs/'.$member->getId());
        $client->submit($crawler->selectButton('Enregistrer')->form(['admin' => '1', 'plain_password' => 'mot de passe bien secret']));
        self::assertResponseRedirects('/admin/utilisateurs');
        $member = $this->em->getRepository(User::class)->find($member->getId());
        self::assertInstanceOf(User::class, $member);
        self::assertContains('ROLE_ADMIN', $member->getRoles());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($member, 'mot de passe bien secret'));

        $crawler = $client->request('GET', '/admin/utilisateurs');
        self::assertSelectorExists('form[action="/admin/utilisateurs/'.$member->getId().'/usurper"]');
    }

    public function testAdminSeriesListShowsPostersAndPaginates(): void
    {
        $admin = $this->user('catalog-admin@example.test', true);
        $this->em->persist($admin);
        for ($number = 1; $number <= 25; ++$number) {
            $this->em->persist(new Series()->setTitle(\sprintf('Série %02d', $number))->setPosterUrl($number === 1 ? '/media/posters/admin.jpg' : null));
        }
        $this->em->flush();
        $client = static::getClient();
        $client->loginUser($admin);
        $client->request('GET', '/admin/series');
        self::assertSelectorExists('.admin-series__poster[src="/media/posters/admin.jpg"]');
        self::assertSelectorExists('.admin-pagination a[href="/admin/series?page=2"]');
        self::assertSelectorTextNotContains('.admin-table', 'Série 25');
    }

    private function user(string $email, bool $admin = false): User
    {
        $user = new User()->setEmail($email)->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'mot de passe initial'));

        return $user;
    }
}
