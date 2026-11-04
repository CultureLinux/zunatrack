<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController extends AbstractController
{
    #[Route('/statistiques', name: 'app_statistics', methods: ['GET'])]
    public function index(StatisticsService $statistics): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();

        return $this->render('statistics/index.html.twig', ['statistics' => $statistics->forUser($user)]);
    }
}
