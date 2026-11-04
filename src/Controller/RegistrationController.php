<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\InstanceConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, InstanceConfigurationService $configuration): Response
    {
        if (!$configuration->canRegister()) {
            throw $this->createNotFoundException();
        }
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser instanceof User) {
                $form->get('email')->addError(new FormError('Un compte existe déjà avec cette adresse e-mail.'));
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte est créé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
    }
}
