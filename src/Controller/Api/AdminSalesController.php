<?php

namespace App\Controller\Api;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/sales')]
#[IsGranted('ROLE_ADMIN')]
class AdminSalesController extends AbstractController
{
    // 🔹 Ventes du jour
    #[Route('/today', methods: ['GET'])]
    public function today(EntityManagerInterface $em): JsonResponse
    {
        $todayStart = new \DateTime('today');
        $todayEnd = new \DateTime('tomorrow');

        $orders = $em->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.createdAt BETWEEN :start AND :end')
            ->setParameter('status', 'Livrée')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getResult();

        $total = 0;
        $products = [];

        foreach ($orders as $order) {
            $total += $order->getTotalPrice();

            foreach ($order->getOrderItems() as $item) {
                $name = $item->getProduit()->getName();

                if (!isset($products[$name])) {
                    $products[$name] = 0;
                }

                $products[$name] += $item->getQuantity();
            }
        }

        return $this->json([
            'date' => $todayStart->format('Y-m-d'),
            'totalSales' => $total,
            'products' => $products,
        ]);
    }
}
