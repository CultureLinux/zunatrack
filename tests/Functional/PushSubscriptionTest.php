<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Service\InstanceConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final class PushSubscriptionTest extends WebTestCase {
 private EntityManagerInterface $em;
 protected function setUp():void { self::ensureKernelShutdown(); static::createClient(); $this->em=self::getContainer()->get(EntityManagerInterface::class); $this->em->getConnection()->executeStatement('DELETE FROM push_subscription'); $this->em->getConnection()->executeStatement('DELETE FROM instance_configuration'); $this->em->getConnection()->executeStatement('DELETE FROM user'); }
 public function testOwnerCanEnrollAndImmediatelyRemoveCurrentDevice():void { $user=$this->user(); $this->em->persist($user);$this->em->flush();self::getContainer()->get(InstanceConfigurationService::class)->updateVapid('mailto:admin@example.test','test-public-key','test-private-key');$client=static::getClient();$client->loginUser($user);$crawler=$client->request('GET','/preferences');self::assertSelectorExists('[data-push-subscription]');$token=$crawler->filter('meta[name="csrf-push-subscription"]')->attr('content');$payload=json_encode(['endpoint'=>'https://push.example.test/a','keys'=>['p256dh'=>'key','auth'=>'auth']],JSON_THROW_ON_ERROR);$client->request('POST','/api/push-subscription',server:['CONTENT_TYPE'=>'application/json','HTTP_X_CSRF_TOKEN'=>$token,'HTTP_USER_AGENT'=>'Desktop Browser Test'],content:$payload);self::assertResponseIsSuccessful();$device=$this->em->getRepository(PushSubscription::class)->findOneBy(['user'=>$user]);self::assertNotNull($device);self::assertSame('Desktop Browser Test',$device->getUserAgent());$client->request('DELETE','/api/push-subscription',server:['CONTENT_TYPE'=>'application/json','HTTP_X_CSRF_TOKEN'=>$token],content:$payload);self::assertResponseIsSuccessful();self::assertNull($this->em->getRepository(PushSubscription::class)->findOneBy(['user'=>$user])); }
 private function user():User{$u=(new User())->setEmail('viewer@example.test');$u->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($u,'a secure password'));return $u;}
}
