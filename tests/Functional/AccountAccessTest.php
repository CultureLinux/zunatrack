<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

    public function testAnonymousVisitorsSeeThePublicHomepageAndCannotReachTheLibrary(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.landing-page');
        self::assertSelectorTextContains('h1', 'Vos séries,');
        self::assertSelectorTextContains('h1', 'au bon moment.');
        self::assertSelectorExists('img[src="/images/zunatrack-banner-v2.png"]');
        self::assertSelectorExists('a[href="/inscription"]');
        self::assertSelectorExists('.landing-nav__actions');
        self::assertSelectorExists('link[rel="icon"]');

        foreach (['/connexion', '/inscription', '/mot-de-passe-oublie'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', '/bibliotheque');
        self::assertResponseRedirects('/connexion');
    }

    public function testAnonymousHomepageShowsLocalSeriesAndOmitsTheClosingDataBlock(): void
    {
        foreach (['library_entry', 'catalog_source', 'series'] as $table) {
            $this->entityManager->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $series = new Series()->setTitle('Public show')->setPosterUrl('/media/posters/public.jpg')->setPopularity(99);
        $this->entityManager->persist($series);
        $this->entityManager->flush();

        $this->client->request('GET', '/');

        self::assertSelectorExists('.landing-series-carousel');
        self::assertSelectorExists('.landing-series-carousel img[src="/media/posters/public.jpg"]');
        self::assertSelectorExists('.landing-series-carousel a[href="/series/'.$series->getId().'"]');
        self::assertSelectorTextNotContains('.landing-page', 'Une application, vos données');
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

        $crawler = $this->client->request('GET', '/preferences');
        self::assertSelectorExists('.accent-swatch[data-accent="blue"]');
        self::assertSelectorExists('select[name="accent"] option[value="slate"]');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['locale' => 'fr', 'accent' => 'cyan']));
        self::assertResponseRedirects('/preferences');
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('html[data-accent="cyan"]');

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'moi@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('cyan', $user->getAccent());
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

    public function testLoginCreatesARememberMeCookieValidForFifteenDays(): void
    {
        $user = new User();
        $user->setEmail('remember@example.test');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'une phrase de passe solide'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/connexion');
        self::assertSelectorExists('input[name="_remember_me"][type="checkbox"]');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'remember@example.test',
            '_password' => 'une phrase de passe solide',
            '_remember_me' => '1',
        ]));

        self::assertResponseRedirects('/');
        $cookies = $this->client->getResponse()->headers->getCookies();
        $rememberMe = array_values(array_filter($cookies, static fn ($cookie): bool => 'REMEMBERME' === $cookie->getName()));
        self::assertCount(1, $rememberMe);
        self::assertSame(1296000, $rememberMe[0]->getMaxAge());
    }

    public function testLoginWithoutRememberMeDoesNotCreateAPersistentCookie(): void
    {
        $user = new User();
        $user->setEmail('session@example.test');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'une phrase de passe solide'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/connexion');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($csrfToken);
        $this->client->request('POST', '/connexion', [
            '_username' => 'session@example.test',
            '_password' => 'une phrase de passe solide',
            '_csrf_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/');
        $cookies = $this->client->getResponse()->headers->getCookies();
        $rememberMe = array_filter($cookies, static fn ($cookie): bool => 'REMEMBERME' === $cookie->getName());
        self::assertCount(0, array_filter($rememberMe, static fn ($cookie): bool => $cookie->getMaxAge() > 0));
    }

    public function testMobileMenuIncludesTheAccountExitAction(): void
    {
        $user = new User()->setEmail('mobile@example.test')->setRoles(['ROLE_USER']);
        $user->setPassword('not-used-by-login-user');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        self::assertSelectorExists('.mobile-menu a[href="/preferences"]');
        self::assertSelectorExists('.mobile-menu form[action="/deconnexion"] button[type="submit"]');
    }
}
