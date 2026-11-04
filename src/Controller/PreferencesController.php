<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Service\InstanceConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PreferencesController extends AbstractController
{
    #[Route('/preferences', name: 'app_preferences')]
    public function __invoke(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, InstanceConfigurationService $configuration): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('preferences', $request->request->getString('_token'))) throw $this->createAccessDeniedException();
            if ($request->request->has('save_preferences')) {
                foreach (['locale', 'accent'] as $field) { $value=$request->request->getString($field); if (in_array($value, ['fr','en','cyan','orange','emerald','blue','violet','rose','red','amber','lime','slate'], true)) { $setter='set'.ucfirst($field); $user->$setter($value); } }
                $em->flush(); $this->addFlash('success', 'Préférences enregistrées.');
            }
            if ($request->request->has('change_password')) {
                $current=$request->request->getString('current_password'); $next=$request->request->getString('new_password');
                if (!$hasher->isPasswordValid($user, $current)) $this->addFlash('error', 'Mot de passe actuel incorrect.');
                elseif (mb_strlen($next) < 12) $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
                else { $user->setPassword($hasher->hashPassword($user, $next)); $em->flush(); $this->addFlash('success', 'Mot de passe modifié.'); }
            }
            return $this->redirectToRoute('app_preferences');
        }
        $key = $configuration->publicVapidKey();
        return $this->render('preferences/index.html.twig', ['user'=>$user, 'pushPublicKey'=>$key, 'pushDevices'=>$key === '' ? [] : $em->getRepository(PushSubscription::class)->findBy(['user'=>$user], ['activatedAt'=>'DESC'])]);
    }
}
