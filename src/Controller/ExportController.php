<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Import\ZunaTrackCsvExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ExportController extends AbstractController
{
    #[Route('/exports/zunatrack', name: 'app_export_zunatrack', methods: ['GET'])]
    public function __invoke(ZunaTrackCsvExporter $exporter): StreamedResponse { $user=$this->getUser(); if(!$user instanceof User) throw $this->createAccessDeniedException(); return $exporter->stream($user); }
}
