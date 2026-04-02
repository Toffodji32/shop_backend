<?php

namespace App\Controller\Api;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TempAdminController extends AbstractController
{
    #[Route('/api/temp-promote/{email}', methods: ['GET'])]
    public function promote(
        string $email,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        // Chercher les rôles ROLE_ADMIN et ROLE_MANAGER
        $roleAdmin = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']);
        $roleManager = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_MANAGER']);

        if ($roleAdmin) {
            $user->addUserRole($roleAdmin);
        }
        if ($roleManager) {
            $user->addUserRole($roleManager);
        }

        $em->flush();

        $roles = array_map(fn($r) => $r->getName(), $user->getUserRoles()->toArray());

        return $this->json([
            'message' => "✅ {$email} promu admin !",
            'roles' => $roles
        ]);
    }
}