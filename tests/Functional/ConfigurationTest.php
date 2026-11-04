<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\InstanceConfiguration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ConfigurationTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM instance_configuration');
        $connection->executeStatement('DELETE FROM user');
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM instance_configuration');
        parent::tearDown();
    }

    public function testOnlyAnAdministratorCanConfigureTheInstance(): void
    {
        $member = $this->user('member@example.test');
        $admin = $this->user('admin@example.test', true);
        $this->em->persist($member);
        $this->em->persist($admin);
        $this->em->flush();

        $client = static::getClient();
        $client->loginUser($member);
        $client->request('GET', '/admin/configuration');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/configuration');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/admin/configuration/inscriptions"]');
        self::assertSelectorExists('form[action="/admin/configuration/vapid"]');
    }

    public function testClosingRegistrationAndReachingTheLimitBlocksNewAccounts(): void
    {
        $admin = $this->user('admin@example.test', true);
        $this->em->persist($admin);
        $this->em->flush();

        $client = static::getClient();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/configuration');
        $form = $crawler->selectButton('Enregistrer les inscriptions')->form(['account_limit' => '1']);
        $form['public_registration']->untick();
        $client->submit($form);
        self::assertResponseRedirects('/admin/configuration');

        $client->request('GET', '/inscription');
        self::assertResponseStatusCodeSame(404);

        $configuration = $this->em->getRepository(InstanceConfiguration::class)->find(1);
        self::assertInstanceOf(InstanceConfiguration::class, $configuration);
        self::assertFalse($configuration->isPublicRegistration());
        self::assertSame(1, $configuration->getAccountLimit());
    }

    private function user(string $email, bool $admin = false): User
    {
        $user = (new User())->setEmail($email)->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'a secure password'));
        return $user;
    }
}
