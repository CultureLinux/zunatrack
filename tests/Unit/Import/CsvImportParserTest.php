<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\CsvImportParser;
use PHPUnit\Framework\TestCase;

final class CsvImportParserTest extends TestCase
{
    public function testGroupsYamtrackTelevisionRowsAndExplicitWatchedEpisodes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yamtrack-');
        self::assertNotFalse($path);
        file_put_contents($path, <<<'CSV'
            "media_id","source","media_type","title","image","season_number","episode_number","score","status","notes","start_date","end_date","progress","created_at","progressed_at"
            "42","tmdb","tv","Example Show","","","","8","Completed","Imported note","2026-01-01 10:00:00+00:00","2026-01-02 10:00:00+00:00","2","",""
            "42","tmdb","season","Example Show","","1","","","Completed","","","","2","",""
            "42","tmdb","episode","Example Show","","1","1","","","","","2026-01-02 10:00:00+00:00","","",""
            "42","tmdb","episode","Example Show","","1","2","","","","","2026-01-02 10:00:00+00:00","","",""
            "7","igdb","game","Not a show","","","","","Completed","","","","","",""
            CSV);

        try {
            $import = new CsvImportParser()->parse($path);

            self::assertSame('yamtrack', $import->format);
            self::assertCount(1, $import->items);
            self::assertSame('tmdb:42', $import->items[0]->externalKey);
            self::assertSame('Example Show', $import->items[0]->title);
            self::assertSame('Completed', $import->items[0]->status);
            self::assertSame('Imported note', $import->items[0]->notes);
            self::assertSame([1 => [1, 2]], $import->items[0]->watchedEpisodes);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsAnUnknownCsvHeader(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yamtrack-');
        self::assertNotFalse($path);
        file_put_contents($path, "title,progress\nBroken,4\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            new CsvImportParser()->parse($path);
        } finally {
            unlink($path);
        }
    }

    public function testParsesTheVersionedZunaTrackExport(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zunatrack-');
        self::assertNotFalse($path);
        file_put_contents($path, "zunatrack_version,provider,external_id,media_type,title,status,notes,start_date,end_date,season_number,episode_number,watched_at\nzunatrack-v1,tvmaze,42,tv,Example Show,watching,,, ,,,\nzunatrack-v1,tvmaze,42,episode,Example Show,watching,,,,1,2,2026-01-01T10:00:00+00:00\n");
        try {
            $import = new CsvImportParser()->parse($path);
            self::assertSame('zunatrack', $import->format);
            self::assertSame('tvmaze:42', $import->items[0]->externalKey);
            self::assertSame([1 => [2]], $import->items[0]->watchedEpisodes);
        } finally {
            unlink($path);
        }
    }

    public function testRetainsTheOriginalYamtrackRowsForARemainderExport(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yamtrack-');
        self::assertNotFalse($path);
        file_put_contents($path, "media_id,source,media_type,title,image,season_number,episode_number,score,status,notes,start_date,end_date,progress,created_at,progressed_at\n42,tmdb,tv,Example Show,,,,,Planning,,,,0,,\n42,tmdb,season,Example Show,,1,,,,,,,,,\n42,tmdb,episode,Example Show,,1,2,,,,,,,,\n");

        try {
            $import = new CsvImportParser()->parse($path);

            self::assertSame([
                ['42', 'tmdb', 'tv', 'Example Show', '', '', '', '', 'Planning', '', '', '', '0', '', ''],
                ['42', 'tmdb', 'season', 'Example Show', '', '1', '', '', '', '', '', '', '', '', ''],
                ['42', 'tmdb', 'episode', 'Example Show', '', '1', '2', '', '', '', '', '', '', '', ''],
            ], $import->items[0]->yamtrackRows);
        } finally {
            unlink($path);
        }
    }
}
