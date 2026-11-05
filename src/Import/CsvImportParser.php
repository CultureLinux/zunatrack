<?php

declare(strict_types=1);

namespace App\Import;

final class CsvImportParser
{
    private const MAX_ROWS = 20_000;
    private const YAMTRACK_HEADERS = ['media_id', 'source', 'media_type', 'title', 'image', 'season_number', 'episode_number', 'score', 'status', 'notes', 'start_date', 'end_date', 'progress', 'created_at', 'progressed_at'];
    private const ZUNATRACK_HEADERS = ['zunatrack_version', 'provider', 'external_id', 'media_type', 'title', 'status', 'notes', 'start_date', 'end_date', 'season_number', 'episode_number', 'watched_at'];

    public function parse(string $path): ParsedImport
    {
        $file = fopen($path, 'rb');
        if ($file === false) throw new \InvalidArgumentException('Le fichier d’import est inaccessible.');

        try {
            $headers = fgetcsv($file, 0, ',', '"', '');
            if ($headers === self::ZUNATRACK_HEADERS) return $this->parseZunaTrack($file, $headers);
            if ($headers === false || $headers !== self::YAMTRACK_HEADERS) throw new \InvalidArgumentException('Ce fichier CSV ne correspond pas à un export Yamtrack ou ZunaTrack pris en charge.');
            $columns = array_flip($headers);
            $groups = [];
            $rows=0; while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) { if (++$rows > self::MAX_ROWS) throw new \InvalidArgumentException('Le fichier contient trop de lignes.');
                if (count($row) !== count($headers)) continue;
                $type = $row[$columns['media_type']];
                if (!in_array($type, ['tv', 'season', 'episode'], true)) continue;
                $source = trim($row[$columns['source']]);
                $mediaId = trim($row[$columns['media_id']]);
                if ($source === '' || $mediaId === '') continue;
                $key = $source.':'.$mediaId;
                $groups[$key] ??= ['tv' => null, 'seasons' => [], 'episodes' => [], 'rows' => []];
                $groups[$key]['rows'][] = $row;
                if ($type === 'tv') $groups[$key]['tv'] = $row;
                if ($type === 'season' && ctype_digit($row[$columns['season_number']])) $groups[$key]['seasons'][(int) $row[$columns['season_number']]] = true;
                if ($type === 'episode' && ctype_digit($row[$columns['season_number']]) && ctype_digit($row[$columns['episode_number']])) $groups[$key]['episodes'][(int) $row[$columns['season_number']]][(int) $row[$columns['episode_number']]] = true;
            }

            $items = [];
            foreach ($groups as $key => $group) {
                $tv = $group['tv'];
                if (!is_array($tv)) continue;
                $episodes = [];
                foreach ($group['episodes'] as $season => $numbers) {
                    $episodes[$season] = array_map('intval', array_keys($numbers));
                    sort($episodes[$season]);
                }
                $items[] = new ImportPayload(
                    $key,
                    trim($tv[$columns['title']]),
                    $this->nullable($tv[$columns['status']]),
                    $this->nullable($tv[$columns['notes']]),
                    $this->nullable($tv[$columns['start_date']]),
                    $this->nullable($tv[$columns['end_date']]),
                    array_map('intval', array_keys($group['seasons'])),
                    $episodes,
                    $group['rows'],
                );
            }

            return new ParsedImport('yamtrack', $items);
        } finally {
            fclose($file);
        }
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /** @param list<string> $headers */
    private function parseZunaTrack($file, array $headers): ParsedImport
    {
        $columns=array_flip($headers); $groups=[];
        $rows=0; while (($row=fgetcsv($file,0,',','"','')) !== false) { if (++$rows > self::MAX_ROWS) throw new \InvalidArgumentException('Le fichier contient trop de lignes.');
            if(count($row)!==count($headers) || $row[$columns['zunatrack_version']] !== 'zunatrack-v1') continue;
            $provider=trim($row[$columns['provider']]); $id=trim($row[$columns['external_id']]); if($provider===''||$id==='')continue;
            $key=$provider.':'.$id; $groups[$key] ??= ['tv'=>null,'episodes'=>[]];
            if($row[$columns['media_type']] === 'tv')$groups[$key]['tv']=$row;
            if($row[$columns['media_type']] === 'episode' && ctype_digit($row[$columns['season_number']]) && ctype_digit($row[$columns['episode_number']]))$groups[$key]['episodes'][(int)$row[$columns['season_number']]][(int)$row[$columns['episode_number']]]=true;
        }
        $items=[];foreach($groups as $key=>$group){if(!is_array($group['tv']))continue;$tv=$group['tv'];$episodes=[];foreach($group['episodes'] as $season=>$numbers){$episodes[$season]=array_map('intval',array_keys($numbers));sort($episodes[$season]);}$items[]=new ImportPayload($key,trim($tv[$columns['title']]),$this->nullable($tv[$columns['status']]),$this->nullable($tv[$columns['notes']]),$this->nullable($tv[$columns['start_date']]),$this->nullable($tv[$columns['end_date']]),array_map('intval',array_keys($episodes)),$episodes);}
        return new ParsedImport('zunatrack', $items);
    }
}
