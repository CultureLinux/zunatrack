<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use App\Import\ImportPayload;
use App\Import\ImportManager;
use App\Entity\CatalogSource;
use App\Entity\LibraryEntry;
use App\Entity\Series;
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

    public function testOwnerCanDownloadTheOriginalYamtrackRowsRemainingInABatch(): void
    {
        $client = static::createClient(); $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['import_item', 'import_batch', 'user'] as $table) $em->getConnection()->executeStatement('DELETE FROM '.$table);
        $user=(new User())->setEmail('remainder@example.test');$user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user,'password'));
        $batch=new ImportBatch($user, 'yamtrack');
        new ImportItem($batch, 'tmdb:42', 'Example Show', (new ImportPayload('tmdb:42', 'Example Show', null, null, null, null, [], [], [
            ['42', 'tmdb', 'tv', 'Example Show', '', '', '', '', 'Planning', '', '', '', '0', '', ''],
        ]))->toArray());
        $em->persist($user); $em->persist($batch); $em->flush();

        $client->loginUser($user); $client->request('GET', '/imports/'.$batch->getId().'/statut');
        $data=json_decode((string)$client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);

        self::assertSame('/imports/'.$batch->getId().'/reste-yamtrack', $data['remainderUrl']);
        $client->request('GET', $data['remainderUrl']);
        self::assertResponseIsSuccessful();
        self::assertSame('text/csv; charset=utf-8', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testReuploadShowsSeriesAlreadyImportedForTheSameOwnerAsMerged(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['import_item', 'import_batch', 'user'] as $table) $em->getConnection()->executeStatement('DELETE FROM '.$table);
        $user=(new User())->setEmail('retry@example.test');$user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user,'password'));
        $previous=new ImportBatch($user, 'yamtrack'); $item=new ImportItem($previous, 'tmdb:42', 'Example Show', (new ImportPayload('tmdb:42','Example Show',null,null,null,null,[],[]))->toArray()); $item->complete(ImportItem::STATE_IMPORTED, null); $previous->record(ImportItem::STATE_IMPORTED);
        $em->persist($user);$em->persist($previous);$em->flush();
        $path=tempnam(sys_get_temp_dir(),'retry-');self::assertNotFalse($path);file_put_contents($path,"media_id,source,media_type,title,image,season_number,episode_number,score,status,notes,start_date,end_date,progress,created_at,progressed_at\n42,tmdb,tv,Example Show,,,,,Planning,,,,0,,\n");

        try {
            $batch=self::getContainer()->get(ImportManager::class)->create($user, new UploadedFile($path, 'yamtrack.csv', 'text/csv', null, true));
            self::assertSame(1, $batch->getTotal());
            self::assertSame(ImportBatch::STATE_DONE, $batch->getState());
            self::assertSame(1, $batch->getMerged());
            self::assertSame(ImportItem::STATE_MERGED, $batch->getItems()->first()->getState());
        } finally { unlink($path); }
    }

    public function testOwnerCanExportALibraryEntryWithItsCatalogProvider(): void
    {
        $client = static::createClient(); $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['library_entry', 'catalog_source', 'series', 'user'] as $table) $em->getConnection()->executeStatement('DELETE FROM '.$table);
        $user=(new User())->setEmail('export@example.test');$user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user,'password'));
        $series=(new Series())->setTitle('Export Show'); $source=new CatalogSource('tvmaze', '42', $series); $entry=new LibraryEntry($user, $series);
        $em->persist($user);$em->persist($series);$em->persist($source);$em->persist($entry);$em->flush();

        $client->loginUser($user); $client->request('GET', '/exports/zunatrack');

        self::assertResponseIsSuccessful();
        self::assertSame('text/csv; charset=utf-8', $client->getResponse()->headers->get('Content-Type'));
        self::assertMatchesRegularExpression('/filename="zunatrack-export-\d{8}-\d{6}\.csv"/', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }
}
