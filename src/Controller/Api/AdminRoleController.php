<?php

namespace App\Controller\Api;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class AdminRoleController extends AbstractController
{
    // ========================
    //  Lister tous les rôles
    // ========================
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $roles = $em->getRepository(Role::class)->findAll();
        $data = [];

        foreach ($roles as $role) {
            $data[] = [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'userCount' => $role->getUsers()->count()
            ];
        }

        return $this->json($data);
    }

    // ========================
    //  Créer un nouveau rôle
    // ========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['description'])) {
            return $this->json(['error' => 'Nom et description obligatoires'], 400);
        }

        $role = new Role();
        $role->setName(strtoupper($data['name']));
        $role->setDescription($data['description']);

        $em->persist($role);
        $em->flush();

        return $this->json([
            'message' => 'Rôle créé',
            'role' => [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
            ]
        ], 201);
    }

    // ========================
    //  Assigner un rôle (AJOUT)
    // ========================
    #[Route('/assign', methods: ['POST'])]
    public function assignRole(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id'], $data['role_id'])) {
            return $this->json(['error' => 'user_id et role_id obligatoires'], 400);
        }

        $user = $em->getRepository(User::class)->find($data['user_id']);
        $role = $em->getRepository(Role::class)->find($data['role_id']);

        if (!$user || !$role) {
            return $this->json(['error' => 'Utilisateur ou rôle introuvable'], 404);
        }

        $user->addUserRole($role);
        $em->flush();

        return $this->json([
            'message' => "Rôle {$role->getName()} assigné à {$user->getEmail()}"
        ]);
    }

    // ======================================================
    //  🔥 Mettre à jour COMPLETEMENT les rôles d'un user
    // ======================================================
    #[Route('/user/{id}', methods: ['PUT'])]
    public function updateUserRoles(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['error' => 'roles array obligatoire'], 400);
        }

        // 🔥 1️⃣ Supprimer tous les rôles actuels
        foreach ($user->getUserRoles() as $existingRole) {
            $user->removeUserRole($existingRole);
        }

        // 🔥 2️⃣ Ajouter uniquement ceux envoyés
        foreach ($data['roles'] as $roleId) {
            $role = $em->getRepository(Role::class)->find($roleId);
            if ($role) {
                $user->addUserRole($role);
            }
        }

        $em->flush();

        return $this->json([
            'message' => 'Rôles mis à jour avec succès'
        ]);
    }

    // ========================
    //  Supprimer un rôle
    // ========================
    #[Route('/{id}', methods: ['DELETE'])]
    public function deleteRole(Role $role, EntityManagerInterface $em): JsonResponse
    {
        if ($role->getUsers()->count() > 0) {
            return $this->json([
                'error' => 'Impossible de supprimer ce rôle car il est assigné à un ou plusieurs utilisateurs'
            ], 400);
        }

        $em->remove($role);
        $em->flush();

        return $this->json([
            'message' => "Rôle {$role->getName()} supprimé avec succès"
        ]);
    }
}