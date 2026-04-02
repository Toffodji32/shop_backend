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
    #[Route('/api/temp-create-roles', methods: ['GET'])]
    public function createRoles(EntityManagerInterface $em): JsonResponse
    {
        $rolesData = [
            ['name' => 'ROLE_ADMIN', 'description' => 'Administrateur'],
            ['name' => 'ROLE_MANAGER', 'description' => 'Manager'],
            ['name' => 'ROLE_USER', 'description' => 'Utilisateur'],
        ];

        $created = [];
        foreach ($rolesData as $roleData) {
            $existing = $em->getRepository(Role::class)->findOneBy(['name' => $roleData['name']]);
            if (!$existing) {
                $role = new Role();
                $role->setName($roleData['name']);
                $role->setDescription($roleData['description']);
                $em->persist($role);
                $created[] = $roleData['name'];
            }
        }

        $em->flush();

        return $this->json(['message' => 'Rôles créés', 'created' => $created]);
    }

    #[Route('/api/temp-promote/{email}', methods: ['GET'])]
    public function promote(
        string $email,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

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