<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\Category;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    // ============================
    // Liste tous les produits + filtres
    // ============================
    #[Route('', methods: ['GET'])]
    public function index(Request $request, ProductRepository $repo): JsonResponse
    {
        $categoryId = $request->query->get('category');
        $search = $request->query->get('search');

        $products = $repo->findByFilters($categoryId, $search);

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->serializeProduct($product);
        }

        return $this->json($data);
    }

    // ============================
    // Détail produit
    // ============================
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    // ============================
    // Création produit (ADMIN)
    // ============================
    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {

        $name = $request->request->get('name');
        $price = $request->request->get('price');
        $description = $request->request->get('description');
        $categorieId = $request->request->get('categorie_id');

        if (!$name || !$price || !$categorieId) {
            return $this->json(['error' => 'Champs obligatoires manquants'], 400);
        }

        $category = $em->getRepository(Category::class)->find($categorieId);
        if (!$category) {
            return $this->json(['error' => 'Catégorie invalide'], 400);
        }

        $product = new Product();
        $product->setName($name);
        $product->setPrice($price);
        $product->setDescription($description);
        $product->setCategorie($category);

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $upload = $this->handleImageUpload($imageFile, $slugger);
            if ($upload instanceof JsonResponse) {
                return $upload;
            }
            $product->setImage($upload);
        }

        $em->persist($product);
        $em->flush();

        return $this->json([
            'message' => 'Produit créé',
            'product' => $this->serializeProduct($product)
        ], 201);
    }

    // ============================
    // Modifier produit (ADMIN)
    // ============================
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['POST'])]
    public function update(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {

        $name = $request->request->get('name');
        $price = $request->request->get('price');
        $description = $request->request->get('description');
        $categorieId = $request->request->get('categorie_id');

        if ($name) $product->setName($name);
        if ($price) $product->setPrice($price);
        if ($description) $product->setDescription($description);

        if ($categorieId) {
            $category = $em->getRepository(Category::class)->find($categorieId);
            if (!$category) {
                return $this->json(['error' => 'Catégorie invalide'], 400);
            }
            $product->setCategorie($category);
        }

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            if ($product->getImage()) {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $product->getImage();
                if (file_exists($oldPath)) unlink($oldPath);
            }

            $upload = $this->handleImageUpload($imageFile, $slugger);
            if ($upload instanceof JsonResponse) return $upload;
            $product->setImage($upload);
        }

        $em->flush();

        return $this->json([
            'message' => 'Produit modifié',
            'product' => $this->serializeProduct($product)
        ]);
    }

    // ============================
    // Supprimer produit (ADMIN)
    // ============================
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        if ($product->getImage()) {
            $path = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $product->getImage();
            if (file_exists($path)) unlink($path);
        }

        $em->remove($product);
        $em->flush();

        return $this->json(['message' => 'Produit supprimé']);
    }

    // ============================
    // Méthodes privées
    // ============================

    private function serializeProduct(Product $product): array
    {
        $baseUrl = $this->getParameter('app.base_url');

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'description' => $product->getDescription(),
            'image' => $product->getImage() ? $baseUrl . '/uploads/' . $product->getImage() : null,
            'categorie' => $product->getCategorie() ? [
                'id' => $product->getCategorie()->getId(),
                'name' => $product->getCategorie()->getName(),
            ] : null,
        ];
    }

    private function handleImageUpload($imageFile, SluggerInterface $slugger)
    {
        if ($imageFile->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'Image trop grande (max 2MB)'], 400);
        }

        if (!in_array($imageFile->getMimeType(), self::ALLOWED_TYPES)) {
            return $this->json(['error' => 'Type de fichier interdit'], 400);
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $imageFile->move($this->getParameter('kernel.project_dir') . '/public/uploads', $newFilename);
        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur upload'], 500);
        }

        return $newFilename;
    }
}
