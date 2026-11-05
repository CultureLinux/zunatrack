<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportTest extends WebTestCase
{
    public function testPreferencesExposeTheOwnerOnlyImportModalAndExport(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['import_item', 'import_batch', 'user'] as $table) $em->getConnection()->executeStatement('DELETE FROM '.$table);
        $user = (new User())->setEmail('importer@example.test');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $em->persist($user); $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/preferences');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="import"]');
        self::assertSelectorExists('input[type="file"][accept*=".csv"]');
        self::assertSelectorExists('a[href="/exports/zunatrack"]');
    }

    public function testOwnerCanUploadAYamtrackCsvAndReceiveABatchStatusUrl(): void
    {
        $client = static::createClient(); $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['import_item', 'import_batch', 'messenger_messages', 'user'] as $table) $em->getConnection()->executeStatement('DELETE FROM '.$table);
        $user=(new User())->setEmail('upload@example.test');$user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user,'password'));$em->persist($user);$em->flush();
        $path=tempnam(sys_get_temp_dir(),'upload-');self::assertNotFalse($path);file_put_contents($path,"\"media_id\",\"source\",\"media_type\",\"title\",\"image\",\"season_number\",\"episode_number\",\"score\",\"status\",\"notes\",\"start_date\",\"end_date\",\"progress\",\"created_at\",\"progressed_at\"\n\"42\",\"tmdb\",\"tv\",\"Example Show\",\"\",\"\",\"\",\"\",\"Planning\",\"\",\"\",\"\",\"0\",\"\",\"\"\n");
        try { $client->loginUser($user); $crawler=$client->request('GET','/preferences');$token=$crawler->filter('form[data-import-target="form"] input[name="_token"]')->attr('value');$client->request('POST','/imports',['_token'=>$token],['file'=>new UploadedFile($path,'yamtrack.csv','text/csv',null,true)],['HTTP_ACCEPT'=>'application/json']);self::assertResponseStatusCodeSame(202);$data=json_decode((string)$client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);self::assertIsInt($data['id']);self::assertSame('/imports/'.$data['id'].'/statut',$data['statusUrl']); } finally { unlink($path); }
    }
}
