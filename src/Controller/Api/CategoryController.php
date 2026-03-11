<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    // ============================
    // Liste des catégories
    // ============================
    #[Route('', methods: ['GET'])]
    public function index(CategoryRepository $repo): JsonResponse
    {
        $categories = $repo->findAll();
        $data = [];

        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
            ];
        }

        return $this->json($data);
    }


    // ============================
    // Détail d'une catégorie
    // ============================
    #[Route('/{id}', methods: ['GET'])]
    public function show(Category $category): JsonResponse
    {
        return $this->json([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        ]);
    }


    // ============================
    // Création catégorie (ADMIN)
    // ============================
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? null);

        $em->persist($category);
        $em->flush();

        return $this->json([
            'message' => 'Catégorie créée',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
            ]
        ], 201);
    }


    // ============================
    // Modification catégorie (ADMIN)
    // ============================
    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Category $category,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }

        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? null);

        $em->flush();

        return $this->json([
            'message' => 'Catégorie modifiée',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
            ]
        ]);
    }


    // ============================
    // Suppression catégorie (ADMIN)
    // ============================
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Category $category, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($category);
        $em->flush();

        return $this->json(['message' => 'Catégorie supprimée']);
    }
}
