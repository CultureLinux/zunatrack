<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\CatalogMetrics;
use App\Catalog\CatalogService;
use App\Entity\LibraryEntry;
use App\Entity\Series;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/utilisateurs', name: 'app_admin_users', methods: ['GET'])]
    public function users(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users.html.twig', ['users' => $em->getRepository(User::class)->findBy([], ['email' => 'ASC'])]);
    }

    #[Route('/utilisateurs/{id}', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_'.$user->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }$admin = $request->request->getBoolean('admin');
            if ($user === $this->getUser() && !$admin) {
                $this->addFlash('error', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');
            } else {
                $user->setRoles($admin ? ['ROLE_ADMIN'] : []);
                $password = (string) $request->request->get('plain_password');
                if ($password !== '') {
                    if (mb_strlen($password) < 12) {
                        $this->addFlash('error', 'Le mot de passe doit comporter au moins 12 caractères.');

                        return $this->render('admin/user_edit.html.twig', ['managedUser' => $user]);
                    }$user->setPassword($hasher->hashPassword($user, $password));
                }$em->flush();
                $this->addFlash('success', 'Compte mis à jour.');
            }

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_edit.html.twig', ['managedUser' => $user]);
    }

    #[Route('/utilisateurs/{id}/usurper', name: 'app_admin_user_impersonate', methods: ['POST'])]
    public function impersonate(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_impersonate_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous êtes déjà connecté avec ce compte.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->redirectToRoute('app_home', ['_switch_user' => $user->getUserIdentifier()]);
    }

    #[Route('/series', name: 'app_admin_series', methods: ['GET'])]
    public function series(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $repository = $em->getRepository(Series::class);
        $total = $repository->count([]);
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);
        $series = $repository->createQueryBuilder('series')->orderBy('series.title', 'ASC')->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        return $this->render('admin/series.html.twig', compact('series', 'page', 'pages', 'total'));
    }

    #[Route('/bibliotheque', name: 'app_admin_library', methods: ['GET'])]
    public function library(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/library.html.twig', ['entries' => $em->getRepository(LibraryEntry::class)->findBy([], ['updatedAt' => 'DESC'])]);
    }

    #[Route('/api-tvmaze', name: 'app_admin_tvmaze', methods: ['GET'])]
    public function tvmaze(Request $request, CatalogService $catalog): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $query = trim((string) $request->query->get('q', ''));
        $showId = trim((string) $request->query->get('show', ''));
        $inspect = (string) $request->query->get('inspect', 'show');
        $payload = null;
        $error = null;
        $mode = null;
        try {
            if ($showId !== '') {
                if (!ctype_digit($showId)) {
                    throw new \InvalidArgumentException('L’identifiant TVmaze doit être numérique.');
                }$payload = $inspect === 'cast' ? $catalog->rawCast($showId) : $catalog->rawShow($showId);
                $mode = $inspect === 'cast' ? 'cast' : 'show';
            } elseif ($query !== '') {
                $payload = $catalog->rawSearch($query);
                $mode = 'search';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->render('admin/tvmaze.html.twig', compact('query', 'showId', 'inspect', 'payload', 'error', 'mode'));
    }

    #[Route('/statistiques', name: 'app_admin_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/stats.html.twig');
    }

    #[Route('/api/statistiques', name: 'app_admin_stats_api', methods: ['GET'])]
    public function api(EntityManagerInterface $em, CatalogMetrics $metrics): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $users = $em->getRepository(User::class)->count([]);
        $admins = \count(array_filter($em->getRepository(User::class)->findAll(), fn (User $user) => \in_array('ROLE_ADMIN', $user->getRoles(), true)));

        return $this->json(['users' => $users, 'admins' => $admins, 'series' => $em->getRepository(Series::class)->count([]), 'libraryEntries' => $em->getRepository(LibraryEntry::class)->count([]), 'traffic' => $metrics->totals()]);
    }
}
