<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class YamtrackRemainderExporter
{
    /** @var list<string> */
    private const HEADERS = ['media_id', 'source', 'media_type', 'title', 'image', 'season_number', 'episode_number', 'score', 'status', 'notes', 'start_date', 'end_date', 'progress', 'created_at', 'progressed_at'];

    public function stream(ImportBatch $batch): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($batch): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, self::HEADERS, ',', '"', '');
            foreach ($batch->getItems() as $item) {
                if (in_array($item->getState(), [ImportItem::STATE_IMPORTED, ImportItem::STATE_MERGED], true)) continue;
                foreach (ImportPayload::fromArray($item->getPayload())->yamtrackRows as $row) fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="yamtrack-reliquat.csv"');

        return $response;
    }
}
