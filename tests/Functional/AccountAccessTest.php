<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class AccountAccessTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('DELETE FROM reset_password_request');
        $this->entityManager->getConnection()->executeStatement('DELETE FROM user');
    }

    public function testAnonymousVisitorsCanReachAccountPagesAndNotTheLibrary(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/connexion');

        foreach (['/connexion', '/inscription', '/mot-de-passe-oublie'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }
    }

    public function testRegistrationLoginAndPasswordReset(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => 'moi@example.test',
            'registration_form[plainPassword]' => 'une phrase de passe solide',
        ]));
        self::assertResponseRedirects('/connexion');

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'moi@example.test',
            '_password' => 'une phrase de passe solide',
        ]));
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bienvenue, moi@example.test.');

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'moi@example.test']);
        self::assertInstanceOf(User::class, $user);
        $token = self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($user);

        $this->client->request('GET', '/reinitialiser/'.$token->getToken());
        self::assertResponseRedirects('/reinitialiser');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer le mot de passe')->form([
            'reset_password_form[plainPassword]' => 'une seconde phrase solide',
        ]));
        self::assertResponseRedirects('/connexion');

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'moi@example.test']);
        self::assertInstanceOf(User::class, $user);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->entityManager->refresh($user);
        self::assertTrue($hasher->isPasswordValid($user, 'une seconde phrase solide'));

        $crawler = $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form([
            'forgot_password_request_form[email]' => 'moi@example.test',
        ]));
        self::assertResponseRedirects('/mot-de-passe-oublie/verifiez-vos-emails');
    }
}
