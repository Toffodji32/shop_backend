<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    // ========================
    // 🔹 Créer commande (USER)
    // ========================
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || empty($data['items'])) {
            return $this->json(['error' => 'Aucun produit envoyé'], 400);
        }

        $order = new Order();
        $order->setCustomer($this->getUser());
        $order->setStatus('En cours');
        $order->setCreatedAt(new \DateTime());

        $total = 0;

        foreach ($data['items'] as $item) {
            if (!isset($item['product'], $item['quantity'])) {
                return $this->json(['error' => 'Format incorrect'], 400);
            }

            if ($item['quantity'] <= 0) {
                return $this->json(['error' => 'Quantité invalide'], 400);
            }

            $product = $em->getRepository(Product::class)->find($item['product']);

            if (!$product) {
                return $this->json(['error' => 'Produit non trouvé'], 404);
            }

            $orderItem = new OrderItem();
            $orderItem->setProduit($product);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setPrice($product->getPrice());
            $orderItem->setOrderRef($order);

            $total += $product->getPrice() * $item['quantity'];

            $em->persist($orderItem);
        }

        $order->setTotalPrice($total);

        $em->persist($order);
        $em->flush();

        return $this->json([
            'message' => 'Commande créée',
            'total' => $total
        ], 201);
    }

    // ========================
    // 🔹 Mes commandes (USER)
    // ========================
    #[Route('/me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOrders(EntityManagerInterface $em): JsonResponse
    {
        $orders = $em->getRepository(Order::class)
            ->findBy(['customer' => $this->getUser()]);

        $data = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'product' => $item->getProduit()->getName(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                ];
            }

            $data[] = [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'totalPrice' => $order->getTotalPrice(),
                'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }

        return $this->json($data);
    }

    // ========================
    // 🔹 Liste commandes admin
    // ========================
    #[Route('/admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function allOrders(EntityManagerInterface $em): JsonResponse
    {
        $orders = $em->getRepository(Order::class)->findAll();

        $data = [];
        foreach ($orders as $order) {
            $customer = $order->getCustomer();

            $items = [];
            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'product' => $item->getProduit()->getName(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                ];
            }

            $data[] = [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'totalPrice' => $order->getTotalPrice(),
                'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'customer' => [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'fullName' => $customer->getName(), // <-- ici le vrai nom
                ],
                'items' => $items,
            ];
        }

        return $this->json($data);
    }

    // ========================
    // 🔹 Validation commande (ADMIN)
    // ========================
    #[Route('/{id}/validate', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validateOrder(Order $order, EntityManagerInterface $em): JsonResponse
    {
        $order->setStatus('Livrée');
        $em->flush();

        return $this->json(['message' => 'Commande validée']);
    }

    // ========================
    // 🔹 Rejet commande (ADMIN)
    // ========================
    #[Route('/{id}/reject', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectOrder(Order $order, EntityManagerInterface $em): JsonResponse
    {
        $order->setStatus('Rejetée');
        $em->flush();

        return $this->json(['message' => 'Commande rejetée']);
    }
}
