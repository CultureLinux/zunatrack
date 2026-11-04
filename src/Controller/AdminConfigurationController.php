<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InstanceConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/configuration')]
final class AdminConfigurationController extends AbstractController
{
    #[Route('', name: 'app_admin_configuration', methods: ['GET'])]
    public function index(InstanceConfigurationService $configuration): Response { $this->denyAccessUnlessGranted('ROLE_ADMIN'); return $this->render('admin/configuration.html.twig', ['configuration' => $configuration->current()]); }

    #[Route('/inscriptions', name: 'app_admin_configuration_registration', methods: ['POST'])]
    public function registration(Request $request, InstanceConfigurationService $configuration): Response { $this->denyAccessUnlessGranted('ROLE_ADMIN'); if (!$this->isCsrfTokenValid('instance-registration', $request->request->getString('_token'))) throw $this->createAccessDeniedException(); try { $limit=$request->request->getString('account_limit'); if ('' !== $limit && (!ctype_digit($limit) || (string) (int) $limit !== $limit)) throw new \InvalidArgumentException('La limite doit être un entier positif.'); $configuration->updateRegistration($request->request->getBoolean('public_registration'), '' === $limit ? null : (int) $limit); $this->addFlash('success', 'Paramètres d’inscription enregistrés.'); } catch (\InvalidArgumentException $exception) { $this->addFlash('error', $exception->getMessage()); } return $this->redirectToRoute('app_admin_configuration'); }

    #[Route('/vapid', name: 'app_admin_configuration_vapid', methods: ['POST'])]
    public function vapid(Request $request, InstanceConfigurationService $configuration): Response { $this->denyAccessUnlessGranted('ROLE_ADMIN'); if (!$this->isCsrfTokenValid('instance-vapid', $request->request->getString('_token'))) throw $this->createAccessDeniedException(); try { $configuration->updateVapid($request->request->getString('vapid_subject'), $request->request->getString('vapid_public_key'), $request->request->getString('vapid_private_key')); $this->addFlash('success', 'Clés VAPID enregistrées.'); } catch (\InvalidArgumentException $exception) { $this->addFlash('error', $exception->getMessage()); } return $this->redirectToRoute('app_admin_configuration'); }
}
