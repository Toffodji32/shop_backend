<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    //  Liste utilisateurs
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();

        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
            ];
        }

        return $this->json($data);
    }

    //  Créer utilisateur
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return $this->json(['error' => 'Email et mot de passe obligatoires'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);

        $hashedPassword = $hasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $user->setRoles($data['roles'] ?? ['ROLE_USER']);

        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'Utilisateur créé'], 201);
    }

    //  Modifier rôle
    #[Route('/{id}/role', methods: ['PUT'])]
    public function updateRole(User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['roles'])) {
            return $this->json(['error' => 'Rôle obligatoire'], 400);
        }

        $user->setRoles($data['roles']);
        $em->flush();

        return $this->json(['message' => 'Rôle mis à jour']);
    }

    // Modifier utilisateur
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        $em->flush();

        return $this->json(['message' => 'Utilisateur modifié']);
    }
    //  Suppression
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return $this->json(['message' => 'Utilisateur supprimé']);
    }
}
