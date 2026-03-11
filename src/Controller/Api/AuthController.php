<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager
    ) {
        $this->em = $em;
        $this->hasher = $hasher;
        $this->jwtManager = $jwtManager;
    }

    // =========================
    // REGISTER
    // =========================
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return new JsonResponse(['error' => 'Email, nom et mot de passe requis'], 400);
        }

        // Vérifie si email existe
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, $data['password']));

        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse(['message' => 'Compte créé'], 201);
    }

    // =========================
    // LOGIN
    // =========================
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Email et mot de passe requis'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->hasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Email ou mot de passe incorrect'], 401);
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles()
            ]
        ]);
    }

    // =========================
    // CURRENT USER
    // =========================
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles()
        ]);
    }

    // =========================
    // 🔥 SETTINGS (UPDATE USER)
    // =========================
    #[Route('/me', name: 'api_update_me', methods: ['PUT'])]
    public function updateMe(
        Request $request,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        // 🔹 Modifier nom
        if (!empty($data['name'])) {
            $user->setName($data['name']);
        }

        // 🔹 Modifier email
        if (!empty($data['email'])) {

            $existingUser = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $data['email']]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Email déjà utilisé'], 400);
            }

            $user->setEmail($data['email']);
        }

        // 🔐 Modifier mot de passe sécurisé
        if (!empty($data['oldPassword']) && !empty($data['password'])) {

            if (!$this->hasher->isPasswordValid($user, $data['oldPassword'])) {
                return new JsonResponse(['error' => 'Ancien mot de passe incorrect'], 400);
            }

            if (strlen($data['password']) < 6) {
                return new JsonResponse(['error' => 'Mot de passe trop court'], 400);
            }

            $user->setPassword(
                $this->hasher->hashPassword($user, $data['password'])
            );
        }

        $this->em->flush();

        return new JsonResponse([
            'message' => 'Profil mis à jour',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName()
            ]
        ]);
    }
}
