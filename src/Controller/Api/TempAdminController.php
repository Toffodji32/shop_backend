<?php

namespace App\Controller\Api;

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

        $user->setRoles(['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_USER']);
        $em->flush();

        return $this->json(['message' => "✅ {$email} est maintenant admin !"]);
    }
}