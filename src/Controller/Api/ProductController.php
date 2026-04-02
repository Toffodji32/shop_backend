<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\Category;
use App\Repository\ProductRepository;
use Cloudinary\Cloudinary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
                'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
            ]
        ]);
    }

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

    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($this->serializeProduct($product));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $name        = $request->request->get('name');
        $price       = $request->request->get('price');
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
            $imageUrl = $this->uploadToCloudinary($imageFile);
            if ($imageUrl instanceof JsonResponse) return $imageUrl;
            $product->setImage($imageUrl);
        }

        $em->persist($product);
        $em->flush();

        return $this->json([
            'message' => 'Produit créé',
            'product' => $this->serializeProduct($product)
        ], 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['POST'])]
    public function update(
        Product $product,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $name        = $request->request->get('name');
        $price       = $request->request->get('price');
        $description = $request->request->get('description');
        $categorieId = $request->request->get('categorie_id');

        if ($name)        $product->setName($name);
        if ($price)       $product->setPrice($price);
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
            $imageUrl = $this->uploadToCloudinary($imageFile);
            if ($imageUrl instanceof JsonResponse) return $imageUrl;
            $product->setImage($imageUrl);
        }

        $em->flush();

        return $this->json([
            'message' => 'Produit modifié',
            'product' => $this->serializeProduct($product)
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($product);
        $em->flush();

        return $this->json(['message' => 'Produit supprimé']);
    }

    private function serializeProduct(Product $product): array
    {
        return [
            'id'          => $product->getId(),
            'name'        => $product->getName(),
            'price'       => $product->getPrice(),
            'description' => $product->getDescription(),
            'image'       => $product->getImage(),
            'categorie'   => $product->getCategorie() ? [
                'id'   => $product->getCategorie()->getId(),
                'name' => $product->getCategorie()->getName(),
            ] : null,
        ];
    }

    private function uploadToCloudinary($imageFile): string|JsonResponse
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if ($imageFile->getSize() > $maxSize) {
            return $this->json(['error' => 'Image trop grande (max 2MB)'], 400);
        }

        if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
            return $this->json(['error' => 'Type de fichier interdit'], 400);
        }

        try {
            $result = $this->cloudinary->uploadApi()->upload(
                $imageFile->getPathname(),
                ['folder' => 'shop_backend']
            );
            return $result['secure_url'];
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur upload Cloudinary : ' . $e->getMessage()], 500);
        }
    }
}